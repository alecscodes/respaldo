import { ref } from 'vue';

interface UploadOptions {
    url: string;
    file: File;
    chunkSize?: number;
    maxConcurrency?: number;
    maxRetries?: number;
    onSuccess?: () => void;
    onError?: (error: string) => void;
    onProgress?: (percentage: number) => void;
}

const DEFAULT_CHUNK_SIZE = 50 * 1024 * 1024; // 50MB
const DEFAULT_MAX_CONCURRENCY = 3; // Upload 3 chunks in parallel
const DEFAULT_MAX_RETRIES = 3;

export function useLargeFileUpload() {
    const isUploading = ref(false);
    const progress = ref(0);
    const error = ref<string | null>(null);
    let abortController: AbortController | null = null;

    const cleanup = (): void => {
        isUploading.value = false;
        document.documentElement.removeAttribute('data-uploading');
        abortController = null;
    };

    const updateProgress = (percentage: number): void => {
        progress.value = Math.max(0, Math.min(100, Math.round(percentage)));
    };

    const getCsrfToken = (): string => {
        const token = document.querySelector<HTMLMetaElement>(
            'meta[name="csrf-token"]',
        )?.content;
        if (!token) {
            throw new Error('CSRF token not found. Please refresh the page.');
        }
        return token;
    };

    const uploadChunk = async (
        appId: string,
        uploadId: string,
        chunkIndex: number,
        chunkBlob: Blob,
        signal: AbortSignal,
        maxRetries: number,
        refreshToken: () => string,
    ): Promise<void> => {
        let lastError: Error | null = null;

        for (let attempt = 0; attempt <= maxRetries; attempt++) {
            if (signal.aborted) {
                throw new Error('Upload cancelled');
            }

            try {
                const formData = new FormData();
                formData.append('upload_id', uploadId);
                formData.append('chunk_index', chunkIndex.toString());
                formData.append('chunk', chunkBlob);

                // Create timeout abort controller
                const timeoutController = new AbortController();
                const timeoutId = setTimeout(() => timeoutController.abort(), 300000); // 5 minutes

                // Use timeout signal, but check main signal too
                if (signal.aborted) {
                    clearTimeout(timeoutId);
                    throw new Error('Upload cancelled');
                }

                signal.addEventListener('abort', () => {
                    clearTimeout(timeoutId);
                    timeoutController.abort();
                });

                const chunkResponse = await fetch(
                    `/apps/${appId}/backups/chunked/upload`,
                    {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': refreshToken(),
                            'X-Requested-With': 'XMLHttpRequest',
                            Accept: 'application/json',
                        },
                        body: formData,
                        signal: timeoutController.signal,
                    },
                );

                clearTimeout(timeoutId);

                if (!chunkResponse.ok) {
                    const data = await chunkResponse.json().catch(() => ({}));
                    throw new Error(
                        data.error || `Failed to upload chunk ${chunkIndex}`,
                    );
                }

                return;
            } catch (err) {
                lastError =
                    err instanceof Error
                        ? err
                        : new Error(`Upload attempt ${attempt + 1} failed`);

                if (signal.aborted) {
                    throw lastError;
                }

                if (attempt < maxRetries) {
                    await new Promise((resolve) =>
                        setTimeout(
                            resolve,
                            Math.min(1000 * 2 ** attempt, 10000),
                        ),
                    );
                }
            }
        }

        throw lastError || new Error(`Failed to upload chunk ${chunkIndex}`);
    };

    const upload = async ({
        url,
        file,
        chunkSize = DEFAULT_CHUNK_SIZE,
        maxConcurrency = DEFAULT_MAX_CONCURRENCY,
        maxRetries = DEFAULT_MAX_RETRIES,
        onSuccess,
        onError,
        onProgress,
    }: UploadOptions): Promise<void> => {
        try {
            isUploading.value = true;
            progress.value = 0;
            error.value = null;
            document.documentElement.setAttribute('data-uploading', '');

            abortController = new AbortController();
            const signal = abortController.signal;

            const refreshCsrfToken = (): string => getCsrfToken();

            // Extract app ID from URL
            const appIdMatch = url.match(/\/apps\/(\d+)\//);
            if (!appIdMatch) {
                throw new Error('Invalid upload URL format');
            }
            const appId = appIdMatch[1];

            // Initialize upload
            const initResponse = await fetch(
                `/apps/${appId}/backups/chunked/init`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': refreshCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        filename: file.name,
                        total_size: file.size,
                        chunk_size: chunkSize,
                    }),
                    signal,
                },
            );

            if (!initResponse.ok) {
                const data = await initResponse.json().catch(() => ({}));
                throw new Error(
                    data.error ||
                        `Failed to initialize upload (${initResponse.status})`,
                );
            }

            const { upload_id: uploadId, total_chunks: totalChunks } =
                await initResponse.json();
            const totalChunksNum = Number(totalChunks);

            // Upload chunks with parallel processing
            const chunkQueue: number[] = Array.from(
                { length: totalChunksNum },
                (_, i) => i,
            );
            const uploadedChunks = new Set<number>();
            const failedChunks: number[] = [];
            let queueIndex = 0;

            const uploadWorker = async (): Promise<void> => {
                while (queueIndex < chunkQueue.length && !signal.aborted) {
                    const chunkIndex = chunkQueue[queueIndex++];
                    const start = chunkIndex * chunkSize;
                    const end = Math.min(start + chunkSize, file.size);

                    try {
                        await uploadChunk(
                            appId,
                            uploadId,
                            chunkIndex,
                            file.slice(start, end),
                            signal,
                            maxRetries,
                            refreshCsrfToken,
                        );

                        uploadedChunks.add(chunkIndex);
                        const percentage =
                            (uploadedChunks.size / totalChunksNum) * 100;
                        updateProgress(percentage);
                        onProgress?.(percentage);
                    } catch {
                        if (!signal.aborted) {
                            failedChunks.push(chunkIndex);
                            chunkQueue.push(chunkIndex); // Retry
                        }
                    }
                }
            };

            // Start parallel workers
            await Promise.all(
                Array.from({ length: maxConcurrency }, () => uploadWorker()),
            );

            if (failedChunks.length > 0 && !signal.aborted) {
                throw new Error(
                    `Failed to upload ${failedChunks.length} chunk(s)`,
                );
            }

            if (signal.aborted) {
                throw new Error('Upload cancelled');
            }

            // Finalize upload
            const finalizeResponse = await fetch(
                `/apps/${appId}/backups/chunked/finalize`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': refreshCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ upload_id: uploadId }),
                    signal,
                },
            );

            if (!finalizeResponse.ok) {
                const data = await finalizeResponse.json().catch(() => ({}));
                throw new Error(data.error || 'Failed to finalize upload');
            }

            updateProgress(100);
            onProgress?.(100);
            cleanup();
            onSuccess?.();
        } catch (err) {
            const errorMessage =
                err instanceof Error ? err.message : 'Upload failed';
            error.value = errorMessage;
            cleanup();
            onError?.(errorMessage);
            throw err;
        }
    };

    const cancel = (): void => {
        if (abortController) {
            abortController.abort();
        }
        cleanup();
    };

    const reset = (): void => {
        cancel();
        progress.value = 0;
        error.value = null;
    };

    return { isUploading, progress, error, upload, cancel, reset };
}
