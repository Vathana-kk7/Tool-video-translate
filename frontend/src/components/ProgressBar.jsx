const ProgressBar = ({ 
  progress = 0, 
  status = 'pending',
  showLabel = true,
  size = 'md' 
}) => {
  const getStatusColor = () => {
    switch (status) {
      case 'completed': return 'from-green-400 to-green-600'
      case 'failed': return 'from-red-400 to-red-600'
      case 'processing': return 'from-blue-400 to-blue-600'
      default: return 'from-gray-300 to-gray-400'
    }
  }

  const getStatusMessage = () => {
    switch (status) {
      case 'pending': return 'Pending'
      case 'extracting_audio': return 'Extracting audio'
      case 'transcribing': return 'Transcribing speech'
      case 'translating': return 'Translating text'
      case 'generating_tts': return 'Generating Khmer voice'
      case 'merging': return 'Merging audio with video'
      case 'completed': return 'Completed'
      case 'failed': return 'Failed'
      default: return 'Processing'
    }
  }

  const sizeClasses = {
    sm: 'h-1.5',
    md: 'h-2.5',
    lg: 'h-4',
  }

  return (
    <div className="w-full">
      {showLabel && (
        <div className="flex justify-between items-center mb-2">
          <span className="text-sm font-medium text-gray-700">
            {getStatusMessage()}
          </span>
          <span className="text-sm font-bold text-primary-600">
            {Math.round(progress)}%
          </span>
        </div>
      )}
      
      <div className={`progress-bar ${sizeClasses[size] || sizeClasses.md}`}>
        <div 
          className={`h-full bg-gradient-to-r ${getStatusColor()} transition-all duration-300 ease-out rounded-full ${
            status === 'completed' ? 'shadow-lg' : ''
          }`}
          style={{ width: `${Math.min(progress, 100)}%` }}
        />
      </div>

      {/* Status indicators */}
      {status === 'processing' && progress > 0 && (
        <div className="mt-2 flex items-center space-x-2 text-xs text-gray-500">
          <div className="w-2 h-2 bg-blue-500 rounded-full animate-pulse" />
          <span>Processing... Please wait</span>
        </div>
      )}

      {status === 'completed' && (
        <div className="mt-2 flex items-center space-x-2 text-xs text-green-600">
          <div className="w-2 h-2 bg-green-500 rounded-full" />
          <span>Ready for download</span>
        </div>
      )}
    </div>
  )
}

export default ProgressBar