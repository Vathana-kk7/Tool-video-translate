import api, { resolveMediaUrl } from './api'

const mediaFields = [
  'original_video_url',
  'final_video_url',
  'khmer_audio_url',
  'subtitle_url',
  'thumbnail_url',
]

const normalizeMediaUrls = (response) => {
  const data = response?.data?.data
  const normalizeVideo = (video) => {
    if (!video || typeof video !== 'object') return video

    for (const field of mediaFields) {
      if (video[field]) {
        video[field] = resolveMediaUrl(video[field])
      }
    }
    return video
  }

  if (Array.isArray(data)) {
    data.forEach(normalizeVideo)
  } else if (data && typeof data === 'object') {
    normalizeVideo(data)
  }

  return response
}

class VideoService {
  /**
   * Upload video for translation
   */
  async uploadVideo(file, videoName, onProgress) {
    const formData = new FormData()
    formData.append('video', file)
    formData.append('video_name', videoName)
    const startedAt = performance.now()

    return await api.post('/videos/upload', formData, {
      timeout: 0,
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
    return normalizeMediaUrls(await api.get(`/videos/${videoId}/status`))
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
   * Rebuild a translated video with a selected background audio volume.
   */
  async updateBackgroundAudio(videoId, backgroundAudioVolume) {
    return await api.post(`/videos/${videoId}/background-audio`, {
      background_audio_volume: backgroundAudioVolume,
    })
  }

  /**
   * Get all videos
   */
  async getAllVideos() {
    return normalizeMediaUrls(await api.get('/videos'))
  }

  /**
   * Get single video details
   */
  async getVideo(videoId) {
    return normalizeMediaUrls(await api.get(`/videos/${videoId}`))
  }
}

export default new VideoService()