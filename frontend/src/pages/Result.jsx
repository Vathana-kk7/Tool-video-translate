import { useEffect, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { HomeIcon, DocumentTextIcon, ArrowUpTrayIcon } from '@heroicons/react/24/outline'
import VideoPreview from '../components/VideoPreview'
import AudioPlayer from '../components/AudioPlayer'
import SubtitleDisplay from '../components/SubtitleDisplay'
import DownloadButton from '../components/DownloadButton'
import StatusCard from '../components/StatusCard'
import useVideoStore from '../contexts/videoStore'
import videoService from '../services/videoService'
import toast from 'react-hot-toast'

const Result = () => {
  const { id } = useParams()
  const { videoDetails, loadVideoDetails, setVideoDetails, error } = useVideoStore()
  const [loading, setLoading] = useState(true)
  const [backgroundVolume, setBackgroundVolume] = useState(50)
  const [applyingBackgroundVolume, setApplyingBackgroundVolume] = useState(false)

  useEffect(() => {
    if (id) {
      loadVideoDetails(id)
        .then(() => setLoading(false))
        .catch(() => setLoading(false))
    }
  }, [id, loadVideoDetails])

  useEffect(() => {
    if (videoDetails?.id === Number(id) && videoDetails.background_audio_volume != null) {
      setBackgroundVolume(videoDetails.background_audio_volume)
    }
  }, [id, videoDetails?.id, videoDetails?.background_audio_volume])

  useEffect(() => {
    if (!id || videoDetails?.status !== 'merging') return undefined

    let active = true
    let timeout
    const poll = async () => {
      try {
        const response = await videoService.getStatus(id)
        const statusData = response.data?.data ?? response.data
        if (!active) return

        if (statusData.status === 'completed' || statusData.status === 'failed') {
          await loadVideoDetails(id)
          if (applyingBackgroundVolume) {
            if (statusData.error_message) {
              toast.error(statusData.error_message)
            } else if (statusData.status === 'completed') {
              toast.success('Background audio updated. The downloaded video now uses this level.')
            }
            setApplyingBackgroundVolume(false)
          }
          return
        }

        timeout = setTimeout(poll, 3000)
      } catch (pollError) {
        if (active) {
          toast.error(pollError.response?.data?.message || 'Could not check the audio update status.')
          setApplyingBackgroundVolume(false)
        }
      }
    }

    poll()
    return () => {
      active = false
      clearTimeout(timeout)
    }
  }, [id, videoDetails?.status, applyingBackgroundVolume, loadVideoDetails])

  const applyBackgroundVolume = async () => {
    setApplyingBackgroundVolume(true)
    try {
      await videoService.updateBackgroundAudio(id, backgroundVolume)
      setVideoDetails({
        ...videoDetails,
        status: 'merging',
        progress: 90,
      })
    } catch (applyError) {
      setApplyingBackgroundVolume(false)
      toast.error(applyError.response?.data?.message || 'Could not update the background audio.')
    }
  }

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
                posterUrl={videoDetails.thumbnail_url}
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
              {videoDetails.video_name && (
                <p className="mt-4 text-center font-semibold text-gray-800">
                  {videoDetails.video_name}
                </p>
              )}
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
              {videoDetails.video_name && (
                <p className="text-lg font-semibold text-gray-800 mb-1">
                  {videoDetails.video_name}
                </p>
              )}
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
                  posterUrl={videoDetails.thumbnail_url}
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
                    posterUrl={videoDetails.thumbnail_url}
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

            {/* Background audio mix */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
              <h2 className="text-gray-900 font-semibold text-lg mb-2">🎼 សម្លេង Background</h2>
              <p className="text-sm text-gray-500 mb-4">
                50% ស្មើកម្រិត Background ដើមក្នុងវីដេអូ។ 100% បង្កើនកម្រិតជា 2 ដង។ ចុច Apply ដើម្បីអនុវត្តទៅលើវីដេអូ Download។
              </p>
              {videoDetails.error_message && (
                <p className="text-sm text-red-600 mb-3" role="alert">
                  {videoDetails.error_message}
                </p>
              )}
              <div className="flex items-center gap-4">
                <input
                  type="range"
                  min="0"
                  max="100"
                  step="1"
                  value={backgroundVolume}
                  onChange={(event) => setBackgroundVolume(Number(event.target.value))}
                  aria-label="Background audio volume"
                  className="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-primary-600"
                />
                <span className="w-12 text-right text-sm font-medium text-gray-700">
                  {backgroundVolume}%
                </span>
              </div>
              <div className="flex items-center justify-between mt-1 text-xs text-gray-500">
                <span>0% · បិទ</span>
                <span>50% · កម្រិតដើម</span>
                <span>កម្រិត Download បច្ចុប្បន្ន: {videoDetails.background_audio_volume ?? 50}%</span>
                <span>100% · 2 ដង</span>
              </div>
              <button
                type="button"
                onClick={applyBackgroundVolume}
                disabled={
                  applyingBackgroundVolume
                  || backgroundVolume === (videoDetails.background_audio_volume ?? 50)
                  || videoDetails.status !== 'completed'
                }
                className="mt-4 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white rounded-xl font-medium transition"
              >
                {applyingBackgroundVolume ? 'កំពុងអនុវត្ត...' : 'Apply និងបង្កើតវីដេអូ Download'}
              </button>
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
