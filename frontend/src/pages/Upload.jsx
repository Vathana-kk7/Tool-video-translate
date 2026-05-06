import { useEffect } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { CheckCircleIcon, ArrowRightIcon, HomeIcon } from '@heroicons/react/24/outline'
import UploadCard from '../components/UploadCard'
import useVideoStore from '../contexts/videoStore'

const Upload = () => {
  const navigate = useNavigate()
  const { status, currentVideo } = useVideoStore()

  useEffect(() => {
    // If video is completed, redirect to result page
    if (status === 'completed' && currentVideo?.id) {
      setTimeout(() => {
        navigate(`/result/${currentVideo.id}`)
      }, 1000)
    }
  }, [status, currentVideo, navigate])

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
            <li className="text-gray-900 font-medium">Upload Video</li>
          </ol>
        </nav>

        {/* Page Header */}
        <div className="text-center mb-12">
          <h1 className="text-4xl font-bold text-gray-900 mb-4">
            Upload Your Chinese Video
          </h1>
          <p className="text-xl text-gray-600 max-w-2xl mx-auto">
            Select your Chinese video file and we&apos;ll automatically translate the speech to Khmer voice
          </p>
        </div>

        {/* Upload Card */}
        <div className="mb-12">
          <UploadCard />
        </div>

        {/* Processing Status */}
        {(status === 'processing' || status === 'completed' || status === 'failed') && (
          <div className="mb-12">
            <div className="max-w-2xl mx-auto">
              <div className="card">
                <div className="flex items-center justify-between mb-6">
                  <h2 className="text-xl font-semibold text-gray-800">
                    Processing Status
                  </h2>
                  <div className="flex items-center space-x-2">
                    {status === 'processing' && (
                      <div className="flex items-center text-blue-600">
                        <svg className="animate-spin h-5 w-5 mr-2" viewBox="0 0 24 24">
                          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                        </svg>
                        Processing
                      </div>
                    )}
                    {status === 'completed' && (
                      <div className="flex items-center text-green-600">
                        <CheckCircleIcon className="w-5 h-5 mr-2" />
                        Completed
                      </div>
                    )}
                    {status === 'failed' && (
                      <div className="flex items-center text-red-600">
                        Failed
                      </div>
                    )}
                  </div>
                </div>

                {/* Progress bars */}
                <div className="space-y-4">
                  <div className="progress-bar">
                    <div 
                      className="progress-bar-fill" 
                      style={{ width: '100%' }}
                    />
                  </div>
                </div>

                <div className="mt-6 flex justify-end">
                  {status === 'completed' && currentVideo?.id && (
                    <Link
                      to={`/result/${currentVideo.id}`}
                      className="btn-primary flex items-center space-x-2"
                    >
                      <span>View Result</span>
                      <ArrowRightIcon className="w-4 h-4" />
                    </Link>
                  )}
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Features Grid */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
          <div className="card">
            <div className="flex items-start space-x-4">
              <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <span className="text-xl">🎯</span>
              </div>
              <div>
                <h3 className="font-semibold text-gray-900 mb-2">Accurate Transcription</h3>
                <p className="text-gray-600 text-sm">
                  OpenAI Whisper provides high-quality Chinese speech-to-text conversion
                </p>
              </div>
            </div>
          </div>

          <div className="card">
            <div className="flex items-start space-x-4">
              <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <span className="text-xl">🌐</span>
              </div>
              <div>
                <h3 className="font-semibold text-gray-900 mb-2">Khmer Translation</h3>
                <p className="text-gray-600 text-sm">
                  Neural machine translation ensures natural Khmer language output
                </p>
              </div>
            </div>
          </div>

          <div className="card">
            <div className="flex items-start space-x-4">
              <div className="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <span className="text-xl">🔊</span>
              </div>
              <div>
                <h3 className="font-semibold text-gray-900 mb-2">Natural Voice</h3>
                <p className="text-gray-600 text-sm">
                  Azure AI generates human-like Khmer speech with proper intonation
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default Upload