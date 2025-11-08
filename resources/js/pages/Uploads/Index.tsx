import axios from 'axios';
import { formatDistanceToNow } from 'date-fns';
import { useEffect, useRef, useState } from 'react';

interface CsvImport {
    id: number;
    file_name: string;
    status: string;
    total_rows: number;
    processed_rows: number;
    progress_percentage: number;
    error_message: string | null;
    started_at: string | null;
    completed_at: string | null;
    created_at: string;
    updated_at: string;
}

interface ImportResponse {
    data: CsvImport[];
}

export default function Index() {
    const [imports, setImports] = useState<CsvImport[]>([]);
    const [uploading, setUploading] = useState(false);
    const [dragActive, setDragActive] = useState(false);
    const [uploadProgress, setUploadProgress] = useState<{ current: number; total: number } | null>(null);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [resetting, setResetting] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const fetchImports = async () => {
        try {
            const response = await axios.get<ImportResponse>('/api/uploads');
            setImports(response.data.data);
        } catch (error) {
            console.error('Failed to fetch imports:', error);
        }
    };

    useEffect(() => {
        fetchImports();

        console.log('Setting up Echo channel for csv-imports...');
        const channel = window.Echo.channel('csv-imports');

        channel.subscribed(() => {
            console.log('Successfully subscribed to csv-imports channel');
        });

        channel.listen('CsvImportProgressUpdated', (event: Partial<CsvImport>) => {
            console.log('Received CsvImportProgressUpdated event:', event);

            setImports((prevImports) => {
                const existingIndex = prevImports.findIndex((imp) => imp.id === event.id);

                if (existingIndex !== -1) {
                    const updated = [...prevImports];
                    updated[existingIndex] = {
                        ...updated[existingIndex],
                        status: event.status ?? updated[existingIndex].status,
                        total_rows: event.total_rows ?? updated[existingIndex].total_rows,
                        processed_rows: event.processed_rows ?? updated[existingIndex].processed_rows,
                        progress_percentage: event.progress_percentage ?? updated[existingIndex].progress_percentage,
                    };
                    console.log('Updated import in list:', updated[existingIndex]);
                    return updated;
                } else {
                    console.log('Import not found in list, fetching all imports...');
                    fetchImports();
                    return prevImports;
                }
            });
        });

        channel.error((error: Error) => {
            console.error('Echo channel error:', error);
        });

        return () => {
            console.log('Cleaning up Echo channel subscription');
            channel.stopListening('CsvImportProgressUpdated');
        };
    }, []);

    const handleFileUpload = async (file: File) => {
        console.log('Starting upload for:', file.name);
        setUploading(true);
        setUploadProgress(null);

        try {
            const chunkSize = 5 * 1024 * 1024; // 5MB chunks
            const totalChunks = Math.ceil(file.size / chunkSize);
            const uploadId = `${Date.now()}-${Math.random().toString(36).substring(7)}`;

            console.log(`Starting upload: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)}MB)`);
            console.log(`Total chunks: ${totalChunks}`);

            // If file is small, use regular upload
            if (file.size < chunkSize) {
                const formData = new FormData();
                formData.append('file', file);

                await axios.post('/api/uploads', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                });
            } else {
                // Use chunked upload for large files
                setUploadProgress({ current: 0, total: totalChunks });

                for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                    const start = chunkIndex * chunkSize;
                    const end = Math.min(start + chunkSize, file.size);
                    const chunk = file.slice(start, end);

                    console.log(`Uploading chunk ${chunkIndex + 1}/${totalChunks} (${(chunk.size / 1024 / 1024).toFixed(2)}MB)`);

                    const formData = new FormData();
                    formData.append('file', chunk);
                    formData.append('chunkIndex', chunkIndex.toString());
                    formData.append('totalChunks', totalChunks.toString());
                    formData.append('fileName', file.name);
                    formData.append('uploadId', uploadId);

                    const response = await axios.post('/api/uploads/chunk', formData, {
                        headers: {
                            'Content-Type': 'multipart/form-data',
                        },
                    });

                    console.log(`Chunk ${chunkIndex + 1} response:`, response.data);

                    setUploadProgress({ current: chunkIndex + 1, total: totalChunks });
                }

                console.log('All chunks uploaded successfully!');
            }

            await fetchImports();
            console.log('Upload complete, clearing file selection');
            setSelectedFile(null);
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
        } catch (error) {
            console.error('Upload error:', error);
            if (axios.isAxiosError(error) && error.response) {
                alert(error.response.data.message || 'Failed to upload file');
            } else {
                alert('Failed to upload file');
            }
        } finally {
            console.log('Setting uploading to false');
            setUploading(false);
            setUploadProgress(null);
        }
    };

    const handleDrag = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.type === 'dragenter' || e.type === 'dragover') {
            setDragActive(true);
        } else if (e.type === 'dragleave') {
            setDragActive(false);
        }
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setDragActive(false);

        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            setSelectedFile(e.dataTransfer.files[0]);
        }
    };

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files[0]) {
            console.log('File selected:', e.target.files[0].name);
            setSelectedFile(e.target.files[0]);
        }
    };

    const handleUpload = () => {
        if (selectedFile) {
            handleFileUpload(selectedFile);
        }
    };

    const handleResetDatabase = async () => {
        if (!confirm('Are you sure you want to reset the database? This will delete all products and CSV imports. This action cannot be undone.')) {
            return;
        }

        setResetting(true);
        try {
            const response = await axios.delete('/api/reset-database');
            alert(`Database reset successfully! Deleted ${response.data.products_deleted} products and ${response.data.imports_deleted} imports.`);
            setSelectedFile(null);
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
            window.location.reload();
        } catch (error) {
            console.error('Reset error:', error);
            if (axios.isAxiosError(error) && error.response) {
                alert(error.response.data.message || 'Failed to reset database');
            } else {
                alert('Failed to reset database');
            }
        } finally {
            setResetting(false);
        }
    };

    const getStatusBadge = (status: string, percentage?: number) => {
        const styles: Record<string, string> = {
            pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            processing: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            completed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        };

        const displayText = status === 'processing' && percentage !== undefined ? `${status} (${percentage.toFixed(0)}%)` : status;

        return <span className={`rounded-full px-3 py-1 text-sm font-medium ${styles[status] || styles.pending}`}>{displayText}</span>;
    };

    return (
        <div className="mx-auto min-h-screen bg-gray-50 px-4 py-12 sm:px-6 lg:px-8 dark:bg-gray-900">
            <div className="mx-auto max-w-7xl space-y-3">
                <div
                    id="upload-container"
                    className={`rounded-lg border-2 border-dashed p-8 transition-colors ${
                        dragActive
                            ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                            : 'border-gray-300 bg-gray-50 dark:border-gray-600 dark:bg-gray-700/50'
                    }`}
                    onDragEnter={handleDrag}
                    onDragLeave={handleDrag}
                    onDragOver={handleDrag}
                    onDrop={handleDrop}
                >
                    <input
                        ref={fileInputRef}
                        type="file"
                        accept=".csv"
                        onChange={handleChange}
                        disabled={uploading}
                        className="hidden"
                        id="file-upload"
                    />
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <label
                            htmlFor="file-upload"
                            className="cursor-pointer rounded-lg p-3 text-gray-700 transition-colors hover:bg-gray-100 md:flex-1 dark:text-gray-300 dark:hover:bg-gray-600/50"
                        >
                            <span className="text-lg font-medium">{selectedFile ? selectedFile.name : 'Select file / Drag and drop'}</span>
                            {selectedFile && (
                                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{(selectedFile.size / 1024 / 1024).toFixed(2)} MB</p>
                            )}
                        </label>
                        <button
                            onClick={handleUpload}
                            disabled={!selectedFile || uploading}
                            className="w-full rounded-lg bg-blue-600 px-6 py-3 font-medium text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-gray-400 md:w-auto md:shrink-0"
                            title={!selectedFile ? 'Please select a file first' : uploading ? 'Upload in progress' : 'Click to upload'}
                        >
                            {uploading
                                ? uploadProgress
                                    ? `Uploading ${uploadProgress.current}/${uploadProgress.total}`
                                    : 'Uploading...'
                                : 'Upload File'}
                        </button>
                    </div>

                    {uploadProgress && (
                        <div className="mt-4">
                            <div className="mb-2 flex justify-between text-sm text-gray-600 dark:text-gray-400">
                                <span>Upload Progress</span>
                                <span>{Math.round((uploadProgress.current / uploadProgress.total) * 100)}%</span>
                            </div>
                            <div className="h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                                <div
                                    className="h-2 rounded-full bg-blue-600 transition-all duration-300"
                                    style={{ width: `${(uploadProgress.current / uploadProgress.total) * 100}%` }}
                                />
                            </div>
                        </div>
                    )}
                </div>

                <div id="history-container" className="overflow-x-auto rounded-lg border border-gray-400">
                    <table className="w-full">
                        <thead className="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th className="px-6 py-4 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                    Time
                                </th>
                                <th className="px-6 py-4 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                    File Name
                                </th>
                                <th className="px-6 py-4 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                            {imports.length === 0 ? (
                                <tr>
                                    <td colSpan={3} className="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                        No uploads yet. Upload a CSV file to get started.
                                    </td>
                                </tr>
                            ) : (
                                imports.map((importItem) => (
                                    <tr key={importItem.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td className="px-6 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            <div className="flex flex-col">
                                                <span className="font-medium text-gray-900 dark:text-white">
                                                    {new Date(importItem.created_at).toLocaleString()}
                                                </span>
                                                <span className="text-xs text-gray-500 dark:text-gray-400">
                                                    {formatDistanceToNow(new Date(importItem.created_at), { addSuffix: true })}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{importItem.file_name}</td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            {getStatusBadge(importItem.status, importItem.progress_percentage)}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="grid flex-wrap items-center gap-2 text-center md:flex">
                    <a
                        className="rounded-lg border-2 border-blue-600 px-4 py-2 text-sm font-medium text-blue-600 transition-colors hover:bg-blue-50 dark:border-blue-400 dark:text-blue-400 dark:hover:bg-blue-900/20"
                        target="_blank"
                        href="/horizon"
                    >
                        Go to Horizon Dashboard
                    </a>
                    <button
                        onClick={handleResetDatabase}
                        disabled={resetting || uploading}
                        className="rounded-lg border-2 border-red-600 px-4 py-2 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 disabled:cursor-not-allowed disabled:border-gray-400 disabled:text-gray-400 dark:border-red-400 dark:text-red-400 dark:hover:bg-red-900/20"
                    >
                        {resetting ? 'Resetting...' : 'Reset Database'}
                    </button>
                </div>
            </div>
        </div>
    );
}
