import { Component } from 'react'

/**
 * Menangkap error saat render (misal modul gagal diunduh karena koneksi putus)
 * agar aplikasi tidak menjadi layar putih. `fallback` menerima ({ error, reset }).
 */
export default class ErrorBoundary extends Component {
  state = { error: null }

  static getDerivedStateFromError(error) {
    return { error }
  }

  componentDidCatch(error, info) {
    console.error('Terjadi kesalahan tampilan:', error, info.componentStack)
  }

  reset = () => this.setState({ error: null })

  render() {
    if (this.state.error) {
      return this.props.fallback({ error: this.state.error, reset: this.reset })
    }
    return this.props.children
  }
}
