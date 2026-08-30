import { useRef, useState, type DragEvent } from 'react';

interface UploadDropzoneProps {
  onFilesSelected: (files: File[]) => void;
  children?: React.ReactNode;
}

/** Drag-and-drop anywhere in the pane opens the upload flow (§22.3, §22.6). */
export function UploadDropzone({ onFilesSelected, children }: UploadDropzoneProps) {
  const [isDragging, setIsDragging] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  function handleDrop(event: DragEvent<HTMLDivElement>) {
    event.preventDefault();
    setIsDragging(false);
    const files = Array.from(event.dataTransfer.files);
    if (files.length > 0) onFilesSelected(files);
  }

  return (
    <div
      onDragOver={(e) => {
        e.preventDefault();
        setIsDragging(true);
      }}
      onDragLeave={() => setIsDragging(false)}
      onDrop={handleDrop}
      className={`relative min-h-[300px] rounded-lg border-2 border-dashed transition-colors ${
        isDragging ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/30' : 'border-transparent'
      }`}
    >
      {children}
      <input
        ref={inputRef}
        type="file"
        multiple
        className="hidden"
        onChange={(e) => {
          const files = e.target.files ? Array.from(e.target.files) : [];
          if (files.length > 0) onFilesSelected(files);
          e.target.value = '';
        }}
      />
      {isDragging && (
        <div className="pointer-events-none absolute inset-0 flex items-center justify-center rounded-lg bg-blue-50/90 text-sm font-medium text-blue-700 dark:bg-blue-950/80 dark:text-blue-300">
          Drop files to upload
        </div>
      )}
    </div>
  );
}
