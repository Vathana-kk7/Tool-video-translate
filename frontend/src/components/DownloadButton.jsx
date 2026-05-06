import { ArrowDownTrayIcon, PlayIcon } from '@heroicons/react/24/outline'
import useVideoStore from '../contexts/videoStore'

const DownloadButton = ({ videoUrl, videoId, variant = 'primary' }) => {
  const { downloadVideo, status } = useVideoStore()

  const handleDownload = async () => {
    if (videoId) {
      try {
        await downloadVideo(videoId)
      } catch (error) {
        console.error('Download failed:', error)
      }
    }
  }

  const isDisabled = !videoUrl && !videoId

  const baseClasses = "inline-flex items-center justify-center space-x-2 px-6 py-3 font-semibold rounded-lg transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"

  const variantClasses = {
    primary: "btn-primary",
    secondary: "btn-secondary",
    success: "bg-green-600 hover:bg-green-700 text-white",
  }

  if (variant === 'success') {
    return (
      <button
        onClick={handleDownload}
        disabled={isDisabled || status === 'processing'}
        className={`${baseClasses} ${variantClasses[variant]}`}
      >
        <ArrowDownTrayIcon className="w-5 h-5" />
        <span>Download Translated Video</span>
      </button>
    )
  }

  return (
    <a
      href={videoUrl}
      download
      className={`${baseClasses} ${variantClasses[variant]} inline-block`}
      onClick={(e) => {
        if (!videoUrl) {
          e.preventDefault()
        }
      }}
    >
      {status === 'processing' ? (
        <>
          <div className="w-5 h-5 border-2 border-current border-t-transparent rounded-full animate-spin" />
          <span>Processing...</span>
        </>
      ) : (
        <>
          <ArrowDownTrayIcon className="w-5 h-5" />
          <span>Download</span>
        </>
      )}
    </a>
  )
}

export default DownloadButton