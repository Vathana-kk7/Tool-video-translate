import { useEffect, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { HomeIcon, DocumentTextIcon, ArrowUpTrayIcon } from '@heroicons/react/24/outline'
import VideoPreview from '../components/VideoPreview'
import AudioPlayer from '../components/AudioPlayer'
import SubtitleDisplay from '../components/SubtitleDisplay'
import DownloadButton from '../components/DownloadButton'
import StatusCard from '../components/StatusCard'
import useVideoStore from '../contexts/videoStore'

const Result = () => {
  const { id } = useParams()
  const { videoDetails, loadVideoDetails, error } = useVideoStore()
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
      <div className="min-h-screen bg-white flex items-center justify-center">
        <div className="text-center">
          <div className="w-16 h-16 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-4" />
          <p className="text-gray-700 text-lg font-medium">កំពុងទាញយកព័ត៌មាន...</p>
        </div>
      </div>
    )
  }

  if (error || !videoDetails) {
    return (
      <div className="min-h-screen bg-white flex items-center justify-center">
        <div className="text-center bg-gray-50 border border-gray-200 rounded-2xl p-10 max-w-md shadow">
          <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg className="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
          <h2 className="text-2xl font-bold text-gray-900 mb-2">រកមិនឃើញវីដេអូ</h2>
          <p className="text-gray-500 mb-6">{error || 'វីដេអូមិនមាន ឬត្រូវបានលុប'}</p>
          <Link to="/upload" className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-medium transition">
            Upload វីដេអូថ្មី
          </Link>
        </div>
      </div>
    )
  }

  const isProcessing = ['uploading', 'processing', 'extracting_audio', 'transcribing', 'translating', 'generating_tts', 'merging'].includes(videoDetails?.status)

  return (
    <div className="min-h-screen bg-gray-50">

      {/* Header */}
      <div className="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
        <div className="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
          <nav className="flex items-center space-x-2 text-sm text-gray-500">
            <Link to="/" className="hover:text-blue-600 flex items-center gap-1 transition">
              <HomeIcon className="w-4 h-4" /> Home
            </Link>
            <span>/</span>
            <Link to="/upload" className="hover:text-blue-600 transition">Upload</Link>
            <span>/</span>
            <span className="text-gray-900 font-medium">Result</span>
          </nav>
          <Link to="/upload" className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            <ArrowUpTrayIcon className="w-4 h-4" />
            Upload ថ្មី
          </Link>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 py-8">

        {isProcessing ? (
          /* ===== PROCESSING VIEW ===== */
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
              <h2 className="text-gray-900 font-semibold text-lg mb-3">វីដេអូដើម</h2>
              <VideoPreview
                videoUrl={videoDetails.original_video_url}
                title="កំពុងរង់ចាំ Processing..."
              />
            </div>
            <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
              <h2 className="text-gray-900 font-semibold text-lg mb-3">ស្ថានភាព</h2>
              <StatusCard
                status={videoDetails.status}
                progress={videoDetails.progress}
                error={videoDetails.error_message}
              />
            </div>
          </div>
        ) : (
          /* ===== COMPLETED VIEW ===== */
          <div className="space-y-6">

            {/* Success Badge */}
            <div className="text-center py-4">
              <div className="inline-flex items-center gap-2 bg-green-100 text-green-700 px-4 py-2 rounded-full text-sm font-medium mb-3">
                <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                </svg>
                បំលែងបានជោគជ័យ!
              </div>
              <h1 className="text-3xl font-bold text-gray-900">លទ្ធផលការបំលែង</h1>
              <p className="text-gray-500 mt-2">វីដេអូរបស់អ្នកត្រូវបានបំលែងទៅជាភាសាខ្មែរ</p>
            </div>

            {/* Videos Side by Side */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
                <h2 className="text-gray-900 font-semibold text-lg mb-3 flex items-center gap-2">
                  <span className="w-2.5 h-2.5 bg-blue-500 rounded-full"></span>
                  វីដេអូដើម (ភាសាចិន)
                </h2>
                <VideoPreview
                  videoUrl={videoDetails.original_video_url}
                  title="Original Chinese Video"
                />
              </div>

              <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
                <h2 className="text-gray-900 font-semibold text-lg mb-3 flex items-center gap-2">
                  <span className="w-2.5 h-2.5 bg-green-500 rounded-full"></span>
                  វីដេអូបំលែង (ភាសាខ្មែរ)
                </h2>
                {videoDetails.final_video_url ? (
                  <VideoPreview
                    videoUrl={videoDetails.final_video_url}
                    title="Translated Khmer Video"
                  />
                ) : (
                  <div className="aspect-video bg-gray-100 rounded-xl flex items-center justify-center border border-gray-200">
                    <p className="text-gray-400">វីដេអូមិនទាន់បង្កើត</p>
                  </div>
                )}
              </div>
            </div>

            {/* Khmer Audio */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
              <h2 className="text-gray-900 font-semibold text-lg mb-3">🎵 សំឡេងខ្មែរ</h2>
              <AudioPlayer
                audioUrl={videoDetails.khmer_audio_url}
                title="Generated Khmer Voice"
              />
            </div>

            {/* Transcription */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
              <h2 className="text-gray-900 font-semibold text-lg mb-3">📝 អត្ថបទ & ការបំលែង</h2>
              <SubtitleDisplay
                text={videoDetails.transcribed_text}
                translatedText={videoDetails.translated_text}
              />
            </div>

            {/* Actions */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
              <h2 className="text-gray-900 font-semibold text-lg mb-4">⬇️ ទាញយក</h2>
              <div className="flex flex-wrap gap-3">
                <DownloadButton
                  videoId={videoDetails.id}
                  variant="success"
                  disabled={!videoDetails?.final_video_url}
                />
                {videoDetails.original_video_url && (
                    <a
                    href={videoDetails.original_video_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-medium transition border border-gray-200"
                  >
                    មើលវីដេអូដើម
                  </a>
                )}
                {videoDetails.subtitle_url && (
                    <a
                    href={videoDetails.subtitle_url}
                    download
                    className="flex items-center gap-2 px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-medium transition border border-gray-200"
                  >
                    <DocumentTextIcon className="w-5 h-5" />
                    Download SRT
                  </a>
                )}
              </div>
            </div>

            {/* Info Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              {[
                { title: 'គុណភាពការបំលែង', desc: 'AI Translation រក្សាការនិយាយខ្មែរធម្មជាតិ' },
                { title: 'វីដេអូដើមមិនប្រែប្រួល', desc: 'វីដេអូដើមមិនត្រូវបានផ្លាស់ប្តូរ មានតែសំឡេងប្រែប្រួល' },
                { title: 'ឯកសារ Subtitle', desc: 'ទាញយក SRT file សម្រាប់ embedding ឬ synchronization' },
              ].map((card, i) => (
                <div key={i} className="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                  <h3 className="font-semibold text-gray-900 mb-1 text-sm">{card.title}</h3>
                  <p className="text-gray-500 text-sm">{card.desc}</p>
                </div>
              ))}
            </div>

            {/* Bottom */}
            <div className="text-center pt-4 pb-8">
              <Link to="/upload" className="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-xl font-medium transition inline-block">
                បំលែងវីដេអូថ្មី
              </Link>
            </div>

          </div>
        )}
      </div>
    </div>
  )
}

export default Result
