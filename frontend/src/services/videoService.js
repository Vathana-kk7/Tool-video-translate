import api from './api'

class VideoService {
  /**
   * Upload video for translation
   */
  async uploadVideo(file, onProgress) {
    const formData = new FormData()
    formData.append('video', file)
    const startedAt = performance.now()

    return await api.post('/videos/upload', formData, {
      onUploadProgress: (event) => {
        if (event.total && onProgress) {
          const elapsedSeconds = Math.max((performance.now() - startedAt) / 1000, 0.001)
          const bytesPerSecond = event.loaded / elapsedSeconds
          const remainingSeconds = bytesPerSecond > 0
            ? Math.max(0, (event.total - event.loaded) / bytesPerSecond)
            : null

          onProgress(
            Math.round((event.loaded / event.total) * 100),
            { bytesPerSecond, remainingSeconds }
          )
        }
      },
    })
  }

  /**
   * Get video translation status
   */
  async getStatus(videoId) {
    return await api.get(`/videos/${videoId}/status`)
  }

  /**
   * Download processed video
   */
  async getDownloadUrl(videoId) {
    const response = await api.get(`/videos/${videoId}/download`, {
      responseType: 'blob',
    })
    return response
  }

  /**
   * Get all videos
   */
  async getAllVideos() {
    return await api.get('/videos')
  }

  /**
   * Get single video details
   */
  async getVideo(videoId) {
    return await api.get(`/videos/${videoId}`)
  }
}

export default new VideoService()