const VideoPreview = ({ 
  videoUrl, 
  title = 'Video Preview', 
  controls = true,
  autoPlay = false 
}) => {
  if (!videoUrl) {
    return (
      <div className="bg-gray-100 rounded-lg p-8 text-center">
        <div className="flex flex-col items-center space-y-3">
          <svg className="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
          </svg>
          <p className="text-gray-500">No video available</p>
        </div>
      </div>
    )
  }

  return (
    <div className="bg-black rounded-lg overflow-hidden shadow-soft">
      <div className="relative">
        <video
          controls={controls}
          autoPlay={autoPlay}
          className="w-full max-h-[500px] mx-auto"
          preload="metadata"
          poster=""
        >
          <source src={videoUrl} type="video/mp4" />
          <source src={videoUrl} type="video/webm" />
          Your browser does not support the video tag.
        </video>

        {/* Video overlay for title */}
        {title && (
          <div className="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-4">
            <h3 className="text-white font-semibold text-lg">{title}</h3>
          </div>
        )}
      </div>
    </div>
  )
}

export default VideoPreview