export function filesToAttachments(fileList) {
  const files = Array.from(fileList || []).slice(0, 5);

  return Promise.all(files.map((file) => new Promise((resolve, reject) => {
    const reader = new FileReader();

    reader.onload = () => {
      const result = String(reader.result || '');
      resolve({
        name: file.name,
        mime: file.type || 'application/octet-stream',
        size: file.size,
        content_base64: result.includes(',') ? result.split(',').pop() : result,
      });
    };
    reader.onerror = () => reject(reader.error || new Error(`Could not read ${file.name}`));
    reader.readAsDataURL(file);
  })));
}
