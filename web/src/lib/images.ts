/**
 * Any picked photo to a JPEG at most `maxDimension` on its long side, upright. Always re-encoded,
 * even small ones: the server strips EXIF without rotating, so orientation is baked in here (as
 * iOS's jpegData does), and HEIC never reaches the API.
 */
export async function toJpeg(file: Blob, maxDimension = 1600, quality = 0.85): Promise<Blob> {
  const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' })
  const scale = Math.min(1, maxDimension / Math.max(bitmap.width, bitmap.height))
  const canvas = document.createElement('canvas')
  canvas.width = Math.round(bitmap.width * scale)
  canvas.height = Math.round(bitmap.height * scale)
  canvas.getContext('2d')!.drawImage(bitmap, 0, 0, canvas.width, canvas.height)
  bitmap.close()
  return new Promise((resolve, reject) => canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('encode failed'))), 'image/jpeg', quality))
}
