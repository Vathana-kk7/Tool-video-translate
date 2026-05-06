const StatusCard = ({ 
  status, 
  progress,
  error 
}) => {
  const processingSteps = [
    { id: 'extracting_audio', label: 'Extract audio from video', icon: '🔊' },
    { id: 'transcribing', label: 'Transcribe Chinese speech to text', icon: '🎤' },
    { id: 'translating', label: 'Translate text to Khmer', icon: '🌐' },
    { id: 'generating_tts', label: 'Generate Khmer voice', icon: '🗣️' },
    { id: 'merging', label: 'Merge audio with video', icon: '🎬' },
  ]

  const getCurrentStep = () => {
    if (status === 'completed') return processingSteps.length
    return processingSteps.findIndex(step => step.id === status) + 1
  }

  const isError = status === 'failed'

  return (
    <div className="card">
      <h3 className="text-lg font-semibold text-gray-800 mb-6">Processing Status</h3>

      <div className="relative">
        {/* Progress line */}
        {!isError && (
          <div className="absolute left-6 top-6 bottom-6 w-0.5 bg-gray-200">
            <div 
              className="w-full bg-primary-500 transition-all duration-500"
              style={{ height: `${(getCurrentStep() / processingSteps.length) * 100}%` }}
            />
          </div>
        )}

        {/* Steps */}
        <div className="space-y-6">
          {processingSteps.map((step, index) => {
            const stepNumber = index + 1
            const isActive = step.id === status
            const isCompleted = stepNumber < getCurrentStep()
            const isCurrentStep = step.id === status

            return (
              <div key={step.id} className="relative flex items-start space-x-4">
                {/* Step number/icon */}
                <div className={`relative z-10 w-12 h-12 rounded-full flex items-center justify-center text-xl transition-all duration-300 ${
                  isCompleted
                    ? 'bg-green-500 text-white'
                    : isActive
                    ? 'bg-primary-500 text-white animate-pulse'
                    : 'bg-gray-200 text-gray-500'
                }`}>
                  {isCompleted ? '✓' : step.icon}
                </div>

                {/* Step content */}
                <div className="flex-1 pt-2">
                  <div className={`font-medium ${
                    isCurrentStep ? 'text-primary-700' : 
                    isCompleted ? 'text-green-700' : 'text-gray-500'
                  }`}>
                    {step.label}
                  </div>
                  {isCurrentStep && (
                    <div className="text-sm text-gray-500 mt-1">
                      {status === 'extracting_audio' && 'Using FFmpeg to extract audio track...'}
                      {status === 'transcribing' && 'Converting Chinese speech to text using Whisper AI...'}
                      {status === 'translating' && 'Translating text to Khmer using Google Translate...'}
                      {status === 'generating_tts' && 'Creating natural Khmer voice using Azure TTS...'}
                      {status === 'merging' && 'Combining new audio with original video...'}
                    </div>
                  )}
                </div>
              </div>
            )
          })}
        </div>
      </div>

      {/* Progress bar */}
      <div className="mt-8 pt-6 border-t border-gray-200">
        <div className="flex justify-between items-center mb-2">
          <span className="text-sm font-medium text-gray-700">Overall Progress</span>
          <span className="text-sm font-bold text-primary-600">
            {Math.round(progress)}%
          </span>
        </div>
        <div className="progress-bar h-3">
          <div 
            className={`progress-bar-fill ${isError ? 'bg-red-500' : ''}`}
            style={{ width: `${Math.min(progress, 100)}%` }}
          />
        </div>
      </div>

      {/* Error message */}
      {isError && error && (
        <div className="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
          <div className="flex items-start space-x-3">
            <div className="flex-shrink-0">
              <svg className="w-5 h-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
              </svg>
            </div>
            <div>
              <h4 className="text-sm font-medium text-red-800">Processing Failed</h4>
              <p className="text-sm text-red-700 mt-1">{error}</p>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

export default StatusCard