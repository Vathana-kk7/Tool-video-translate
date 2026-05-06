import { useEffect, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { HomeIcon, ArrowDownTrayIcon, PlayIcon, DocumentTextIcon } from '@heroicons/react/24/outline'
import VideoPreview from '../components/VideoPreview'
import AudioPlayer from '../components/AudioPlayer'
import SubtitleDisplay from '../components/SubtitleDisplay'
import DownloadButton from '../components/DownloadButton'
import StatusCard from '../components/StatusCard'
import useVideoStore from '../contexts/videoStore'

const Result = () => {
  const { id } = useParams()
  const { videoDetails, loadVideoDetails, status, error } = useVideoStore()
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (id) {
      loadVideoDetails(id)
        .then(() => setLoading(false))
        .catch(() => setLoading(false))
    }
  }, [id, loadVideoDetails])

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 py-12">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center">
            <svg className="animate-spin h-12 w-12 text-primary-600 mx-auto" viewBox="0 0 24 24">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
            </svg>
            <h3 className="mt-4 text-lg font-medium text-gray-900">Loading video details...</h3>
          </div>
        </div>
      </div>
    )
  }

  if (error || !videoDetails) {
    return (
      <div className="min-h-screen bg-gray-50 py-12">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center">
            <div className="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-6">
              <svg className="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </div>
            <h2 className="text-2xl font-bold text-gray-900 mb-4">Video Not Found</h2>
            <p className="text-gray-600 mb-8">
              {error || 'The video you are looking for does not exist or has been removed.'}
            </p>
            <Link to="/upload" className="btn-primary">
              Upload New Video
            </Link>
          </div>
        </div>
      </div>
    )
  }

  const isProcessing = status === 'processing' || status === 'uploading'

  return (
    <div className="min-h-screen bg-gray-50 py-12">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* Breadcrumb */}
        <nav className="mb-8">
          <ol className="flex items-center space-x-2 text-sm text-gray-500">
            <li>
              <Link to="/" className="hover:text-primary-600 flex items-center">
                <HomeIcon className="w-4 h-4 mr-1" />
                Home
              </Link>
            </li>
            <li>/</li>
            <li>
              <Link to="/upload" className="hover:text-primary-600">
                Upload
              </Link>
            </li>
            <li>/</li>
            <li className="text-gray-900 font-medium">Result</li>
          </ol>
        </nav>

        {/* Header */}
        <div className="mb-8">
          <h1 className="text-4xl font-bold text-gray-900 mb-4">
            Translation Result
          </h1>
          <p className="text-gray-600">
            Your video has been successfully translated to Khmer. View, download, or share below.
          </p>
        </div>

        {isProcessing ? (
          // Processing View
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div>
              <h2 className="text-xl font-semibold text-gray-900 mb-4">
                Original Video
              </h2>
              <VideoPreview 
                videoUrl={videoDetails.original_video_url} 
                title="Waiting for processing... (Preview not available)"
              />
            </div>
            <div>
              <h2 className="text-xl font-semibold text-gray-900 mb-4">
                Processing
              </h2>
              <StatusCard 
                status={videoDetails.status} 
                progress={videoDetails.progress}
                error={videoDetails.error_message}
              />
            </div>
          </div>
        ) : (
          // Completed Result View
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            {/* Left Column - Video */}
            <div className="space-y-6">
              <div>
                <h2 className="text-xl font-semibold text-gray-900 mb-4">
                  Original Video
                </h2>
                <VideoPreview 
                  videoUrl={videoDetails.original_video_url} 
                  title="Original Chinese Video"
                />
              </div>

              <div>
                <h2 className="text-xl font-semibold text-gray-900 mb-4">
                  Translated Video
                </h2>
                <VideoPreview 
                  videoUrl={videoDetails.final_video_url} 
                  title="Khmer Voice Translation"
                />
              </div>
            </div>

            {/* Right Column - Audio and Subtitles */}
            <div className="space-y-6">
              <div>
                <h2 className="text-xl font-semibold text-gray-900 mb-4">
                  Khmer Audio
                </h2>
                <AudioPlayer 
                  audioUrl={videoDetails.khmer_audio_url}
                  title="Generated Khmer Voice"
                />
              </div>

              <div>
                <h2 className="text-xl font-semibold text-gray-900 mb-4">
                  Transcription & Translation
                </h2>
                <SubtitleDisplay 
                  text={videoDetails.transcribed_text}
                  translatedText={videoDetails.translated_text}
                />
              </div>

              <div>
                <h2 className="text-xl font-semibold text-gray-900 mb-4">
                  Actions
                </h2>
                <div className="space-y-3">
                  <DownloadButton 
                    videoId={videoDetails.id}
                    variant="success"
                  />
                  
                  <div className="flex space-x-3">
                    <a
                      href={videoDetails.original_video_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="btn-secondary"
                    >
                      View Original
                    </a>
                    {videoDetails.subtitle_url && (
                      <a
                        href={videoDetails.subtitle_url}
                        download
                        className="btn-secondary"
                      >
                        <DocumentTextIcon className="w-5 h-5 mr-2" />
                        Download SRT
                      </a>
                    )}
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Info Cards */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mt-12">
          <div className="card">
            <h3 className="font-semibold text-gray-900 mb-2">Translation Quality</h3>
            <p className="text-gray-600 text-sm">
              Our AI translation maintains natural Khmer speech patterns and intonation for authentic voiceover.
            </p>
          </div>
          <div className="card">
            <h3 className="font-semibold text-gray-900 mb-2">Original Preserved</h3>
            <p className="text-gray-600 text-sm">
              The original video remains unchanged with only the audio track replaced with Khmer voice.
            </p>
          </div>
          <div className="card">
            <h3 className="font-semibold text-gray-900 mb-2">Subtitle File</h3>
            <p className="text-gray-600 text-sm">
              Download SRT subtitle file for embedding or manual synchronization.
            </p>
          </div>
        </div>

        {/* Bottom Actions */}
        <div className="mt-12 text-center border-t border-gray-200 pt-8">
          <div className="flex justify-center space-x-4">
            <Link to="/upload" className="btn-primary">
              Translate Another Video
            </Link>
          </div>
        </div>
      </div>
    </div>
  )
}

export default Result