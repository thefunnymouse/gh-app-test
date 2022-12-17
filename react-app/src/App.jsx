import { useRef, useState } from "react";
import reactLogo from "./assets/react.svg";
import "./App.css";

function App() {
  const file = useRef();
  const [filenames, setFilenames] = useState([]);

  function updateFileList() {
    const files = file.current.files;
    setFilenames([...files].map(file => file.name));
  }

  function upload() {
    console.log(file.current.files);
    const files = file.current.files;

    var formdata = new FormData();
    [...files].forEach(file => {
      formdata.append("files[]", file, file.name);
    });

    var requestOptions = {
      method: "POST",
      body: formdata,
      redirect: "follow",
    };

    fetch("http://localhost:8000/api/upload", requestOptions)
      .then(response => response.text())
      .then(result => console.log(result))
      .catch(error => console.log("error", error));
  }

  return (
    <>
      <label htmlFor="file">
        <input
          id="file"
          name="file"
          type="file"
          multiple
          ref={file}
          onChange={updateFileList}
          style={{ display: "none" }}
        />
        + Add file
      </label>
      <button onClick={upload}>Upload</button>
      <h4>File list</h4>
      <ul>
        {filenames.map(f => (
          <li key={f}>{f}</li>
        ))}
      </ul>
    </>
  );
}

export default App;
