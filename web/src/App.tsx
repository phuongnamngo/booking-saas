import { useEffect, useState } from 'react'
import axios from 'axios'
import './App.css'

function App() {
  const [status, setStatus] = useState('loading...')

  useEffect(() => {
    axios.get('/api/v1/health')
      .then((r) => setStatus(r.data.status))
      .catch(() => setStatus('error'))
  }, [])
  return <h1>API status: {status}</h1>
}

export default App
