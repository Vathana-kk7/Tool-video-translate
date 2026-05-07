import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { HomeIcon, VideoCameraIcon, PlayIcon, ArrowDownTrayIcon, EyeIcon } from '@heroicons/react/24/outline'
import VideoPreview from '../components/VideoPreview'
import DownloadButton from '../components/DownloadButton'
import videoService from '../services/videoService'

const Videos = () => {
  const [videos, setVideos] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    fetchVideos()
  }, [])

  const fetchVideos = async () => {
    try {
      const response = await videoService.getAllVideos()
      const payload = response?.data

      // API may return either:
      // - an array: [ ... ]
      // - { data: [ ... ] }
      // - a single object: { ... }
      const normalized = Array.isArray(payload)
        ? payload
        : Array.isArray(payload?.data)
          ? payload.data
          : payload
            ? [payload]
            : []

      setVideos(normalized)
    } catch (err) {
      setError(err.message || 'Failed to load videos')
    } finally {
      setLoading(false)
    }
  }

  const getStatusBadge = (status) => {
    const statusConfig = {
      pending: { label: 'Pending', color: 'bg-yellow-100 text-yellow-800' },
      processing: { label: 'Processing', color: 'bg-blue-100 text-blue-800' },
      extracting_audio: { label: 'Extracting Audio', color: 'bg-purple-100 text-purple-800' },
      transcribing: { label: 'Transcribing', color: 'bg-indigo-100 text-indigo-800' },
      translating: { label: 'Translating', color: 'bg-cyan-100 text-cyan-800' },
      generating_tts: { label: 'Generating Voice', color: 'bg-pink-100 text-pink-800' },
      merging: { label: 'Merging', color: 'bg-orange-100 text-orange-800' },
      completed: { label: 'Completed', color: 'bg-green-100 text-green-800' },
      failed: { label: 'Failed', color: 'bg-red-100 text-red-800' },
    }

    const config = statusConfig[status] || statusConfig.pending

    return (
      <span className={`px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${config.color}`}>
        {config.label}
      </span>
    )
  }

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  }

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 py-12">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center">
            <svg className="animate-spin h-12 w-12 text-primary-600 mx-auto" viewBox="0 0 24 24">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
            </svg>
            <h3 className="mt-4 text-lg font-medium text-gray-900">Loading your videos...</h3>
          </div>
        </div>
      </div>
    )
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gray-50 py-12">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center">
            <div className="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-6">
              <svg className="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h2 className="text-2xl font-bold text-gray-900 mb-4">Error Loading Videos</h2>
            <p className="text-gray-600 mb-8">{error}</p>
            <button onClick={fetchVideos} className="btn-primary">
              Try Again
            </button>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-gray-50 py-12">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* Breadcrumb */}
        <nav className="mb-8">
          <ol className="flex items-center space-x-2 text-sm text-gray-500">
            <li>
              <Link to="/" className="flex items-center hover:text-primary-600">
                <HomeIcon className="w-4 h-4 mr-1" />
                Home
              </Link>
            </li>
            <li>/</li>
            <li className="text-gray-900 font-medium flex items-center">
              <VideoCameraIcon className="w-4 h-4 mr-1" />
              My Videos
            </li>
          </ol>
        </nav>

        {/* Page Header */}
        <div className="flex justify-between items-center mb-8">
          <div>
            <h1 className="text-4xl font-bold text-gray-900 mb-2">
              My Videos
            </h1>
            <p className="text-gray-600">
              View and manage your translated videos
            </p>
          </div>
          <Link to="/upload" className="btn-primary">
            Upload New Video
          </Link>
        </div>

        {/* Videos Grid */}
        {videos.length === 0 ? (
          <div className="card text-center py-12">
            <div className="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
              <VideoCameraIcon className="w-10 h-10 text-gray-400" />
            </div>
            <h3 className="text-xl font-semibold text-gray-900 mb-2">
              No Videos Yet
            </h3>
            <p className="text-gray-600 mb-6">
              Upload your first Chinese video to get started with translation
            </p>
            <Link to="/upload" className="btn-primary">
              Upload Your First Video
            </Link>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {videos.map((video) => (
              <div key={video.id} className="card hover:shadow-lg transition-shadow duration-300">
                {/* Video Thumbnail */}
                <div className="mb-4">
                  <VideoPreview
                    videoUrl={video.final_video_url || video.original_video_url}
                    title={`Video #${video.id}`}
                    controls={false}
                  />
                </div>

                {/* Video Info */}
                <div className="space-y-3">
                  <div className="flex justify-between items-start">
                    <div>
                      <p className="text-sm text-gray-500">
                        Video ID: #{video.id}
                      </p>
                      <p className="text-xs text-gray-400">
                        {formatDate(video.created_at)}
                      </p>
                    </div>
                    {getStatusBadge(video.status)}
                  </div>

                  {/* Progress Bar (if processing) */}
                  {video.status !== 'completed' && video.status !== 'failed' && (
                    <div className="progress-bar h-2">
                      <div
                        className="progress-bar-fill"
                        style={{ width: `${video.progress}%` }}
                      />
                    </div>
                  )}

                  {/* Actions */}
                  <div className="flex space-x-2 pt-2 border-t border-gray-100">
                    <Link
                      to={`/result/${video.id}`}
                      className="flex-1 text-center px-3 py-2 bg-primary-50 text-primary-700 rounded-lg hover:bg-primary-100 transition-colors text-sm font-medium flex items-center justify-center space-x-1"
                    >
                      <EyeIcon className="w-4 h-4" />
                      <span>View</span>
                    </Link>
                    {video.status === 'completed' && (
                      <DownloadButton
                        videoId={video.id}
                        videoUrl={video.final_video_url}
                        variant="secondary"
                      />
                    )}
                    {video.status === 'processing' && (
                      <div className="flex-1 text-center px-3 py-2 bg-gray-100 text-gray-500 rounded-lg text-sm font-medium">
                        Processing...
                      </div>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}

export default Videos
