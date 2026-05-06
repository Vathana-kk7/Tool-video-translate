import api from './api'

class VideoService {
  /**
   * Upload video for translation
   */
  async uploadVideo(file) {
    const formData = new FormData()
    formData.append('video', file)

    return await api.post('/videos/upload', formData)
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