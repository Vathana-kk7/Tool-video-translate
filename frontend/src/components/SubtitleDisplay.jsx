import { useState } from 'react'

const SubtitleDisplay = ({ text, translatedText }) => {
  const [showOriginal, setShowOriginal] = useState(true)

  if (!text && !translatedText) {
    return (
      <div className="bg-gray-100 rounded-lg p-6 text-center">
        <p className="text-gray-500">No subtitles available</p>
      </div>
    )
  }

  const containerClasses = "card transition-all duration-300"

  return (
    <div className={containerClasses}>
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-lg font-semibold text-gray-800">Transcription</h3>
        <div className="flex items-center space-x-2">
          {translatedText && (
            <div className="flex bg-gray-100 rounded-lg p-1">
              <button
                onClick={() => setShowOriginal(true)}
                className={`px-3 py-1 text-sm font-medium rounded-md transition-colors ${
                  showOriginal
                    ? 'bg-white shadow text-primary-600'
                    : 'text-gray-600 hover:text-gray-900'
                }`}
              >
                Original (Chinese)
              </button>
              <button
                onClick={() => setShowOriginal(false)}
                className={`px-3 py-1 text-sm font-medium rounded-md transition-colors ${
                  !showOriginal
                    ? 'bg-white shadow text-primary-600'
                    : 'text-gray-600 hover:text-gray-900'
                }`}
              >
                Translation (Khmer)
              </button>
            </div>
          )}
        </div>
      </div>

      <div className="max-h-64 overflow-y-auto">
        {showOriginal && text ? (
          <div className="p-4 bg-blue-50 rounded-lg">
            <p className="text-gray-700 leading-relaxed whitespace-pre-wrap">
              {text}
            </p>
            <div className="mt-2 text-xs text-blue-600 font-medium">
              Original Chinese transcription
            </div>
          </div>
        ) : translatedText ? (
           <div className="p-4 bg-green-50 rounded-lg">
             <p className="text-gray-700 leading-relaxed whitespace-pre-wrap">
               {translatedText}
             </p>
             <div className="mt-2 text-xs text-green-600 font-medium">
               Khmer translation
             </div>
           </div>
        ) : (
          <p className="text-gray-500 text-center">No content available</p>
        )}
      </div>

      {/* Download subtitle file */}
      {translatedText && (
        <div className="mt-4 pt-4 border-t border-gray-200">
          <button className="text-sm text-primary-600 hover:text-primary-700 font-medium flex items-center space-x-1">
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            <span>Download SRT</span>
          </button>
        </div>
      )}
    </div>
  )
}

export default SubtitleDisplay