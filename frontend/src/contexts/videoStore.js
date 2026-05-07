import { create } from 'zustand'
import toast from 'react-hot-toast'
import videoService from '../services/videoService'

const useVideoStore = create((set, get) => ({
  // State
  currentVideo: null,
  uploadProgress: 0,
  processingProgress: 0,
  status: 'idle', // idle, uploading, processing, completed, failed
  error: null,
  videoDetails: null,

  // Actions
  setCurrentVideo: (video) => set({ currentVideo: video }),
  
  setUploadProgress: (progress) => set({ uploadProgress: progress }),
  
  setProcessingProgress: (progress) => set({ processingProgress: progress }),
  
  setStatus: (status) => set({ status }),
  
  setError: (error) => set({ error: error }),
  
  setVideoDetails: (details) => set({ videoDetails: details }),

  reset: () => set({
    currentVideo: null,
    uploadProgress: 0,
    processingProgress: 0,
    status: 'idle',
    error: null,
    videoDetails: null,
  }),

  // Combined actions
  async uploadVideo(file) {
    try {
      set({ 
        status: 'uploading', 
        error: null,
        uploadProgress: 0 
      })
      
      const response = await videoService.uploadVideo(file)
      const responseData = response.data?.data ?? response.data ?? response
      
      set({ 
        status: 'processing',
        uploadProgress: 100,
        currentVideo: {
          id: responseData.video_id,
          status: responseData.status,
        }
      })
      
      toast.success('Video uploaded successfully!')
      
      // Start polling for status
      get().pollVideoStatus(responseData.video_id)
      
      return responseData.video_id
    } catch (error) {
      const errorMessage = error.response?.data?.message || 'Upload failed. Please try again.'
      set({ 
        status: 'failed', 
        error: errorMessage 
      })
      toast.error(errorMessage)
      throw error
    }
  },

  async pollVideoStatus(videoId, interval = 2000, maxAttempts = 180) {
    let attempts = 0
    
    const poll = async () => {
      try {
        const response = await videoService.getStatus(videoId)
        const responseData = response.data?.data ?? response.data ?? response
        const status = responseData.status
        const progress = responseData.progress
        
        set({ 
          processingProgress: progress,
          currentVideo: { 
            id: videoId, 
            status,
            progress 
          }
        })
        
        if (status === 'completed') {
          set({ status: 'completed' })
          toast.success('Video translation completed!')
          get().loadVideoDetails(videoId)
          return
        }
        
        if (status === 'failed') {
          set({ 
            status: 'failed',
            error: responseData.error_message || 'Processing failed'
          })
          toast.error('Video processing failed')
          return
        }
        
        // Continue polling
        attempts++
        if (attempts < maxAttempts) {
          setTimeout(poll, interval)
        } else {
          set({ 
            status: 'failed',
            error: 'Processing timeout. Please contact support.'
          })
        }
      } catch (error) {
        set({ 
          status: 'failed',
          error: error.message
        })
      }
    }
    
    poll()
  },

  async loadVideoDetails(videoId) {
    try {
      const response = await videoService.getVideo(videoId)
      const responseData = response.data?.data ?? response.data ?? response
      set({ 
        videoDetails: responseData,
        status: responseData.status,
        currentVideo: {
          id: responseData.id,
          status: responseData.status,
          progress: responseData.progress,
        },
      })
    } catch (error) {
      console.error('Failed to load video details:', error)
    }
  },

  async downloadVideo(videoId) {
    try {
      const response = await videoService.getDownloadUrl(videoId)
      const responseData = response.data ?? response
      const blob = new Blob([responseData], { type: 'video/mp4' })
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `translated_video_${videoId}.mp4`
      document.body.appendChild(a)
      a.click()
      window.URL.revokeObjectURL(url)
      document.body.removeChild(a)
      toast.success('Download started!')
    } catch (error) {
      const errorMessage = error.response?.data?.message || 'Download failed'
      toast.error(errorMessage)
      throw error
    }
  },
}))

export default useVideoStore