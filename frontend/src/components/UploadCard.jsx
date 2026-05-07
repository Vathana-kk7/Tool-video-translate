import { useState, useCallback } from 'react'
import { useDropzone } from 'react-dropzone'
import { CloudArrowUpIcon, FilmIcon, XMarkIcon } from '@heroicons/react/24/outline'
import useVideoStore from '../contexts/videoStore'

const UploadCard = () => {
  const [isDragging, setIsDragging] = useState(false)
  const { uploadVideo, status, uploadProgress, currentVideo, error } = useVideoStore()

  const onDrop = useCallback((acceptedFiles) => {
    if (acceptedFiles.length > 0) {
      uploadVideo(acceptedFiles[0])
    }
  }, [uploadVideo])

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    accept: {
      'video/*': ['.mp4', '.avi', '.mov', '.mkv', '.webm']
    },
    maxSize: 5 * 1024 * 1024 * 1024, // 5GB
    multiple: false,
    onDragEnter: () => setIsDragging(true),
    onDragLeave: () => setIsDragging(false),
  })

  const getStatusMessage = () => {
    switch (status) {
      case 'uploading':
        return `Uploading... ${Math.round(uploadProgress)}%`
      case 'processing':
        return 'Processing video...'
      case 'completed':
        return 'Translation completed!'
      case 'failed':
        return currentVideo?.id ? 'Processing failed' : 'Upload failed'
      default:
        return isDragActive ? 'Drop the video here...' : 'Drag & drop a video here'
    }
  }

  return (
    <div className="max-w-2xl mx-auto">
      <div
        {...getRootProps()}
        className={`dropzone cursor-pointer transition-all duration-200 ${
          isDragActive ? 'dropzone-active scale-105' : ''
        } ${status === 'uploading' || status === 'processing' ? 'pointer-events-none opacity-75' : ''}`}
      >
        <input {...getInputProps()} />

        <div className="flex flex-col items-center space-y-4">
          <div className={`p-4 rounded-full ${
            isDragActive ? 'bg-primary-100' : 'bg-gray-100'
          }`}>
            <CloudArrowUpIcon className={`w-12 h-12 ${
              isDragActive ? 'text-primary-600' : 'text-gray-400'
            }`} />
          </div>

          <div className="text-center">
            <h3 className="text-lg font-semibold text-gray-700 mb-2">
              {getStatusMessage()}
            </h3>
            <p className="text-sm text-gray-500">
              Drag & drop your Chinese video file here, or click to select
            </p>
<p className="text-xs text-gray-400 mt-2">
               Supported: MP4, AVI, MOV, MKV, WebM (max 5GB)
             </p>
          </div>

          {status === 'uploading' && (
            <div className="w-full max-w-xs">
              <div className="progress-bar">
                <div
                  className="progress-bar-fill"
                  style={{ width: `${uploadProgress}%` }}
                />
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Processing indicator */}
      {status === 'processing' && (
        <div className="mt-6 text-center">
          <div className="inline-flex items-center space-x-2 text-primary-600">
            <svg className="animate-spin h-5 w-5" viewBox="0 0 24 24">
              <circle
                className="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                strokeWidth="4"
                fill="none"
              />
              <path
                className="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
              />
            </svg>
            <span className="font-medium">Processing your video...</span>
          </div>
          <p className="text-sm text-gray-500 mt-2">
            This may take several minutes depending on video length
          </p>
        </div>
      )}

      {status === 'failed' && error && (
        <div className="mt-6 rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">
          <div className="font-semibold text-red-800 mb-1">Processing error</div>
          <p>{error}</p>
        </div>
      )}

      {/* Quick tips */}
      <div className="mt-8 p-4 bg-blue-50 rounded-lg border border-blue-200">
        <h4 className="font-semibold text-blue-900 mb-2">Quick Tips</h4>
<ul className="text-sm text-blue-800 space-y-1">
           <li>• Ensure your video has clear Chinese audio</li>
           <li>• Videos up to 60 minutes are supported (5GB max)</li>
           <li>• Processing usually takes 2-10 minutes depending on length</li>
           <li>• You can download the translated video afterward</li>
         </ul>
      </div>
    </div>
  )
}

export default UploadCard
