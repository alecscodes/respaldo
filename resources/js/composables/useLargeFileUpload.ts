import { ref } from 'vue';

interface UploadOptions {
    url: string;
    file: File;
    chunkSize?: number;
    onSuccess?: () => void;
    onError?: (error: string) => void;
    onProgress?: (percentage: number) => void;
}

const DEFAULT_CHUNK_SIZE = 10 * 1024 * 1024; // 10MB

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

    const upload = async ({
        url,
        file,
        chunkSize = DEFAULT_CHUNK_SIZE,
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
            const csrfToken = getCsrfToken();

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
                        'X-CSRF-TOKEN': csrfToken,
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

            // Upload chunks sequentially
            for (
                let chunkIndex = 0;
                chunkIndex < totalChunksNum;
                chunkIndex++
            ) {
                if (signal.aborted) {
                    throw new Error('Upload cancelled');
                }

                const start = chunkIndex * chunkSize;
                const end = Math.min(start + chunkSize, file.size);
                const formData = new FormData();
                formData.append('upload_id', uploadId);
                formData.append('chunk_index', chunkIndex.toString());
                formData.append('chunk', file.slice(start, end));

                const chunkResponse = await fetch(
                    `/apps/${appId}/backups/chunked/upload`,
                    {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            Accept: 'application/json',
                        },
                        body: formData,
                        signal,
                    },
                );

                if (!chunkResponse.ok) {
                    const data = await chunkResponse.json().catch(() => ({}));
                    throw new Error(
                        data.error || `Failed to upload chunk ${chunkIndex}`,
                    );
                }

                const percentage = ((chunkIndex + 1) / totalChunksNum) * 100;
                updateProgress(percentage);
                onProgress?.(percentage);
            }

            // Finalize upload
            const finalizeResponse = await fetch(
                `/apps/${appId}/backups/chunked/finalize`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
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
