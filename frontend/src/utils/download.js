/**
 * Unduh file dari request Axios (responseType: 'blob').
 * Nama file diambil dari header Content-Disposition yang dikirim Laravel.
 */
export async function downloadFile(request, fallbackName) {
  let response
  try {
    response = await request
  } catch (error) {
    // Respons error juga berbentuk Blob; ubah ke JSON agar pesan error bisa ditampilkan.
    if (error.response?.data instanceof Blob) {
      try {
        error.response.data = JSON.parse(await error.response.data.text())
      } catch {
        // biarkan apa adanya
      }
    }
    throw error
  }

  const disposition = response.headers['content-disposition'] ?? ''
  const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposition)
  const name = match ? decodeURIComponent(match[1]) : fallbackName

  const url = URL.createObjectURL(response.data)
  const link = document.createElement('a')
  link.href = url
  link.download = name
  document.body.appendChild(link)
  link.click()
  link.remove()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}
