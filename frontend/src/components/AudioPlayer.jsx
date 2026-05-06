import { useState, useRef } from 'react'
import { SpeakerWaveIcon, SpeakerXMarkIcon, PlayIcon, PauseIcon } from '@heroicons/react/24/outline'

const AudioPlayer = ({ audioUrl, title = 'Khmer Audio' }) => {
  const [isPlaying, setIsPlaying] = useState(false)
  const [isMuted, setIsMuted] = useState(false)
  const [volume, setVolume] = useState(1)
  const audioRef = useRef(null)

  const togglePlay = () => {
    if (audioRef.current) {
      if (isPlaying) {
        audioRef.current.pause()
      } else {
        audioRef.current.play()
      }
      setIsPlaying(!isPlaying)
    }
  }

  const toggleMute = () => {
    if (audioRef.current) {
      audioRef.current.muted = !isMuted
      setIsMuted(!isMuted)
    }
  }

  const handleVolumeChange = (e) => {
    const newVolume = parseFloat(e.target.value)
    setVolume(newVolume)
    if (audioRef.current) {
      audioRef.current.volume = newVolume
    }
  }

  if (!audioUrl) {
    return (
      <div className="bg-gray-100 rounded-lg p-6 text-center">
        <SpeakerXMarkIcon className="w-12 h-12 text-gray-300 mx-auto mb-3" />
        <p className="text-gray-500">Audio not available</p>
      </div>
    )
  }

  return (
    <div className="card">
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-lg font-semibold text-gray-800">{title}</h3>
        <div className="flex items-center space-x-2">
          <button
            onClick={toggleMute}
            className="p-2 rounded-full hover:bg-gray-100 transition-colors"
          >
            {isMuted ? (
              <SpeakerXMarkIcon className="w-5 h-5 text-gray-600" />
            ) : (
              <SpeakerWaveIcon className="w-5 h-5 text-gray-600" />
            )}
          </button>
        </div>
      </div>

      <audio ref={audioRef} src={audioUrl} onEnded={() => setIsPlaying(false)} />

      <div className="flex items-center space-x-4">
        <button
          onClick={togglePlay}
          className="flex-shrink-0 w-12 h-12 bg-primary-100 hover:bg-primary-200 rounded-full flex items-center justify-center transition-colors"
        >
          {isPlaying ? (
            <PauseIcon className="w-6 h-6 text-primary-600" />
          ) : (
            <PlayIcon className="w-6 h-6 text-primary-600 ml-1" />
          )}
        </button>

        <div className="flex-1">
          <input
            type="range"
            min="0"
            max="1"
            step="0.01"
            value={volume}
            onChange={handleVolumeChange}
            className="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-primary-600"
          />
          <div className="flex justify-between text-xs text-gray-500 mt-1">
            <span>Volume</span>
            <span>{Math.round(volume * 100)}%</span>
          </div>
        </div>
      </div>

      {/* Waveform placeholder */}
      <div className="mt-4 h-16 bg-gray-50 rounded-lg flex items-center justify-center">
        <div className="flex items-center space-x-1">
          {[...Array(50)].map((_, i) => (
            <div
              key={i}
              className="w-1 bg-primary-400 rounded-full"
              style={{
                height: `${Math.random() * 100}%`,
                animation: `pulse ${0.5 + Math.random() * 0.5}s ease-in-out infinite`,
                animationDelay: `${Math.random() * 0.5}s`,
              }}
            />
          ))}
        </div>
      </div>
    </div>
  )
}

export default AudioPlayer