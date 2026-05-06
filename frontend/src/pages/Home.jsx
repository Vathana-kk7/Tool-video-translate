import { Link } from 'react-router-dom'
import { CloudArrowUpIcon, SpeakerWaveIcon, VideoCameraIcon, GlobeAltIcon } from '@heroicons/react/24/outline'

const Home = () => {
  const features = [
    {
      icon: CloudArrowUpIcon,
      title: 'Easy Upload',
      description: 'Drag & drop your Chinese video file. Supports MP4, MOV, MKV up to 500MB.',
      color: 'bg-blue-100 text-blue-600',
    },
    {
      icon: SpeakerWaveIcon,
      title: 'AI Speech Recognition',
      description: 'Powered by OpenAI Whisper for accurate Chinese speech-to-text conversion.',
      color: 'bg-purple-100 text-purple-600',
    },
    {
      icon: GlobeAltIcon,
      title: 'Translation',
      description: 'Professional translation from Chinese to Khmer using Google Translate.',
      color: 'bg-green-100 text-green-600',
    },
    {
      icon: VideoCameraIcon,
      title: 'Natural Voice',
      description: 'Azure AI Text-to-Speech generates natural Khmer voice for the translation.',
      color: 'bg-orange-100 text-orange-600',
    },
  ]

  const stats = [
    { label: 'Videos Processed', value: '10,000+' },
    { label: 'Languages Supported', value: '2' },
    { label: 'Accuracy Rate', value: '95%' },
    { label: 'Processing Time', value: '2-5 min' },
  ]

  return (
    <div className="min-h-screen">
      {/* Hero Section */}
      <section className="bg-gradient-to-br from-primary-600 via-primary-700 to-secondary-600 text-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
          <div className="text-center">
            <h1 className="text-5xl sm:text-7xl font-bold mb-6">
              <span className="block">Chinese to</span>
              <span className="block text-gradient from-yellow-300 to-orange-300">
                Khmer Translator
              </span>
            </h1>
            
            <p className="text-xl sm:text-2xl text-primary-100 mb-10 max-w-3xl mx-auto">
              Transform your Chinese videos into natural-sounding Khmer voice while preserving the original video. 
              Powered by cutting-edge AI technology.
            </p>

            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              <Link
                to="/upload"
                className="px-8 py-4 bg-white text-primary-700 font-bold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 text-lg"
              >
                Start Translating
              </Link>
              <Link
                to="/videos"
                className="px-8 py-4 bg-transparent border-2 border-white text-white font-semibold rounded-xl hover:bg-white hover:text-primary-700 transition-all duration-200 text-lg"
              >
                View My Videos
              </Link>
            </div>
          </div>

          {/* Floating demo video card */}
          <div className="mt-16 relative">
            <div className="absolute inset-0 bg-gradient-to-r from-primary-600 to-secondary-600 blur-3xl opacity-30 transform translate-y-4"></div>
            <div className="relative bg-white/10 backdrop-blur-lg rounded-2xl p-8 border border-white/20">
              <div className="grid grid-cols-3 gap-4 items-center">
                <div className="bg-black rounded-lg p-4 aspect-video flex items-center justify-center">
                  <div className="text-white/50 text-sm">Video Preview</div>
                </div>
                <div className="text-center">
                  <div className="text-4xl mb-2">🇨🇳 → 🇰🇭</div>
                  <p className="text-white/90">Translation</p>
                </div>
                <div className="bg-green-50 rounded-lg p-4 aspect-video flex items-center justify-center">
                  <div className="text-green-700 text-sm font-medium">Translated<br/>Result</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section className="py-20 bg-gray-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-16">
            <h2 className="text-4xl font-bold text-gray-900 mb-4">
              How It Works
            </h2>
            <p className="text-xl text-gray-600 max-w-2xl mx-auto">
              Four simple steps to translate your Chinese video into natural Khmer voice
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            {features.map((feature, index) => {
              const Icon = feature.icon
              return (
                <div key={index} className="card hover:shadow-xl transition-shadow duration-300">
                  <div className={`w-14 h-14 rounded-xl ${feature.color} flex items-center justify-center mb-4`}>
                    <Icon className="w-7 h-7" />
                  </div>
                  <h3 className="text-xl font-semibold text-gray-900 mb-2">
                    {feature.title}
                  </h3>
                  <p className="text-gray-600">
                    {feature.description}
                  </p>
                </div>
              )
            })}
          </div>
        </div>
      </section>

      {/* Stats Section */}
      <section className="py-16 bg-primary-900 text-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
            {stats.map((stat, index) => (
              <div key={index}>
                <div className="text-4xl font-bold text-primary-200 mb-2">
                  {stat.value}
                </div>
                <div className="text-primary-100">
                  {stat.label}
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Tech Stack Section */}
      <section className="py-20 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-16">
            <h2 className="text-4xl font-bold text-gray-900 mb-4">
              Powered by World-Class AI
            </h2>
            <p className="text-xl text-gray-600 max-w-2xl mx-auto">
              We use the latest AI technologies for accurate translation and natural voice synthesis
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div className="text-center p-6 border border-gray-200 rounded-xl hover:shadow-lg transition-shadow">
              <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <span className="text-2xl">🎙️</span>
              </div>
              <h3 className="text-lg font-semibold text-gray-900 mb-2">OpenAI Whisper</h3>
              <p className="text-gray-600">State-of-the-art speech recognition for accurate Chinese transcription</p>
            </div>

            <div className="text-center p-6 border border-gray-200 rounded-xl hover:shadow-lg transition-shadow">
              <div className="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <span className="text-2xl">🌍</span>
              </div>
              <h3 className="text-lg font-semibold text-gray-900 mb-2">Google Translate</h3>
              <p className="text-gray-600">Powered by Google&apos;s neural machine translation for Khmer language</p>
            </div>

            <div className="text-center p-6 border border-gray-200 rounded-xl hover:shadow-lg transition-shadow">
              <div className="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <span className="text-2xl">🗣️</span>
              </div>
              <h3 className="text-lg font-semibold text-gray-900 mb-2">Azure Text-to-Speech</h3>
              <p className="text-gray-600">Natural-sounding Khmer voice synthesis with neural networks</p>
            </div>
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-20 bg-gradient-to-br from-primary-600 to-secondary-600 text-white">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <h2 className="text-4xl font-bold mb-6">
            Ready to Translate Your Video?
          </h2>
          <p className="text-xl text-primary-100 mb-10">
            Upload your Chinese video now and get the Khmer translation in minutes
          </p>
          <Link
            to="/upload"
            className="inline-block px-10 py-4 bg-white text-primary-700 font-bold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 text-lg"
          >
            Upload Your Video Free
          </Link>
        </div>
      </section>

      {/* Footer */}
      <footer className="bg-gray-900 text-gray-300 py-12">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center">
            <div className="flex items-center justify-center space-x-2 mb-4">
              <div className="w-8 h-8 bg-gradient-to-br from-primary-500 to-secondary-500 rounded-lg flex items-center justify-center">
                <span className="text-white font-bold">TR</span>
              </div>
              <span className="text-xl font-bold text-white">
                Chinese to Khmer Translator
              </span>
            </div>
            <p className="text-gray-500">
              © 2024 Video Translation Tool. All rights reserved.
            </p>
            <p className="text-sm text-gray-600 mt-2">
              Powered by OpenAI Whisper, Google Translate, and Azure AI
            </p>
          </div>
        </div>
      </footer>
    </div>
  )
}

export default Home