import { useState, useEffect } from 'react'
import logo from './logo.svg';
import './App.css';

function JsonApiEntrypoint() {
  const [data, setData] = useState(null);
  useEffect(() => {
    fetch(`${process.env.REACT_APP_API_URL}jsonapi`)
      .then(res => res.json())
      .then(json => setData(json));
  }, [])

  if (data === null) {
    return null;
  }

  return <code><pre>{JSON.stringify(data, null, 4)}</pre></code>
}

function App() {
  return (
    <div className="App">
      <header className="App-header">
        <img src={logo} className="App-logo" alt="logo" />
      </header>
      <JsonApiEntrypoint />
    </div>
  );
}

export default App;
