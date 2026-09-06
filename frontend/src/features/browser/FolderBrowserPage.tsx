/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Breadcrumbs } from '../../components/Breadcrumbs';
import { UploadDropzone } from '../../components/UploadDropzone';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { useAuth } from '../auth/useAuth';
import { useFolderChildren, useCreateFolder, useDeleteFolder, useRenameFolder } from './hooks';
import { CreateFolderDialog } from './CreateFolderDialog';
import { RenameFolderDialog } from './RenameFolderDialog';
import { UploadDialog } from '../documents/UploadDialog';
import { DocumentDetailPanel } from '../documents/DocumentDetailPanel';
import type { Document, Folder } from '../../types/api';

function FolderIcon() {
  return <span aria-hidden>📁</span>;
}
function DocumentIcon() {
  return <span aria-hidden>📄</span>;
}

export function FolderBrowserPage() {
  const { folderId: folderIdParam } = useParams<{ folderId?: string }>();
  const folderId = folderIdParam ? Number(folderIdParam) : null;
  const navigate = useNavigate();
  const { user } = useAuth();

  const { data, isLoading, isError } = useFolderChildren(folderId);
  const createFolder = useCreateFolder(folderId);
  const deleteFolder = useDeleteFolder(folderId);
  const renameFolder = useRenameFolder(folderId);

  const [showCreateFolder, setShowCreateFolder] = useState(false);
  const [pendingUploadFiles, setPendingUploadFiles] = useState<File[] | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<Folder | null>(null);
  const [renameTarget, setRenameTarget] = useState<Folder | null>(null);
  const [openDocumentId, setOpenDocumentId] = useState<number | null>(null);
  const [autoOpenShare, setAutoOpenShare] = useState(false);

  function openDocumentShare(id: number) {
    setAutoOpenShare(true);
    setOpenDocumentId(id);
  }

  function openFolder(id: number) {
    navigate(`/browse/${id}`);
  }

  function handleFilesSelected(files: File[]) {
    if (folderId === null) return; // can't upload at the very root — pick a folder first
    setPendingUploadFiles(files);
  }

  const folders = data?.folders ?? [];
  const documents = data?.documents ?? [];
  const isEmpty = !isLoading && folders.length === 0 && documents.length === 0;

  return (
    <div>
      <div className="mb-4">
        <Breadcrumbs entries={data?.breadcrumb ?? []} />
      </div>

      <div>
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <h1 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
            {data?.breadcrumb.at(-1)?.name ?? 'Home'}
          </h1>
          <div className="flex gap-2">
            {/* Only ADMINs may create root folders (§16) — hidden, not disabled, per §23. */}
            {(folderId !== null || user?.role === 'ADMIN') && (
              <button
                onClick={() => setShowCreateFolder(true)}
                className="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
              >
                New folder
              </button>
            )}
            {folderId !== null && (
              <label className="cursor-pointer rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                Upload
                <input
                  type="file"
                  multiple
                  className="hidden"
                  onChange={(e) => {
                    const files = e.target.files ? Array.from(e.target.files) : [];
                    if (files.length > 0) handleFilesSelected(files);
                    e.target.value = '';
                  }}
                />
              </label>
            )}
          </div>
        </div>

        <UploadDropzone onFilesSelected={handleFilesSelected}>
          {isLoading && (
            <div className="space-y-2" aria-label="Loading">
              {[...Array(4)].map((_, i) => (
                <div key={i} className="h-12 animate-pulse rounded-md bg-slate-200 dark:bg-slate-700" />
              ))}
            </div>
          )}

          {isError && (
            <p className="rounded-md bg-red-50 p-4 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
              Couldn't load this folder.
            </p>
          )}

          {isEmpty && (
            <div className="flex flex-col items-center justify-center gap-3 py-16 text-center">
              <p className="text-sm text-slate-500 dark:text-slate-400">This folder is empty</p>
              {folderId !== null && (
                <label className="cursor-pointer rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                  Upload files
                  <input
                    type="file"
                    multiple
                    className="hidden"
                    onChange={(e) => {
                      const files = e.target.files ? Array.from(e.target.files) : [];
                      if (files.length > 0) handleFilesSelected(files);
                      e.target.value = '';
                    }}
                  />
                </label>
              )}
            </div>
          )}

          {!isLoading && !isError && !isEmpty && (
            <ul className="divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-800">
              {folders.map((folder) => (
                <li
                  key={`folder-${folder.id}`}
                  className="flex cursor-pointer items-center justify-between px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/50"
                  onClick={() => openFolder(folder.id)}
                >
                  <span className="flex items-center gap-2 text-sm text-slate-800 dark:text-slate-200">
                    <FolderIcon /> {folder.name}
                  </span>
                  <span className="flex gap-3">
                    <button
                      onClick={(e) => {
                        e.stopPropagation();
                        setRenameTarget(folder);
                      }}
                      className="text-xs text-slate-500 hover:underline dark:text-slate-400"
                    >
                      Rename
                    </button>
                    <button
                      onClick={(e) => {
                        e.stopPropagation();
                        setDeleteTarget(folder);
                      }}
                      className="text-xs text-red-600 hover:underline"
                    >
                      Delete
                    </button>
                  </span>
                </li>
              ))}

              {documents.map((doc: Document) => (
                <li
                  key={`doc-${doc.id}`}
                  className="flex cursor-pointer items-center justify-between px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/50"
                  onClick={() => setOpenDocumentId(doc.id)}
                >
                  <span className="flex items-center gap-2 text-sm text-slate-800 dark:text-slate-200">
                    <DocumentIcon /> {doc.name}
                    <span className="text-xs text-slate-400">v{doc.currentVersion}</span>
                  </span>
                  <button
                    onClick={(e) => {
                      e.stopPropagation();
                      openDocumentShare(doc.id);
                    }}
                    className="text-xs text-slate-500 hover:underline dark:text-slate-400"
                  >
                    Share
                  </button>
                </li>
              ))}
            </ul>
          )}
        </UploadDropzone>
      </div>

      {showCreateFolder && (
        <CreateFolderDialog onCreate={(name) => createFolder.mutateAsync(name)} onClose={() => setShowCreateFolder(false)} />
      )}

      {pendingUploadFiles && folderId !== null && (
        <UploadDialog folderId={folderId} initialFiles={pendingUploadFiles} onClose={() => setPendingUploadFiles(null)} />
      )}

      {renameTarget && (
        <RenameFolderDialog
          folder={renameTarget}
          onRename={(name) => renameFolder.mutateAsync({ id: renameTarget.id, name })}
          onClose={() => setRenameTarget(null)}
        />
      )}

      {deleteTarget && (
        <ConfirmDialog
          title={`Delete "${deleteTarget.name}"?`}
          message="Moves to Trash — can be restored within 30 days."
          confirmLabel="Delete"
          onConfirm={async () => {
            await deleteFolder.mutateAsync(deleteTarget.id);
            setDeleteTarget(null);
          }}
          onCancel={() => setDeleteTarget(null)}
        />
      )}

      {openDocumentId !== null && folderId !== null && (
        <DocumentDetailPanel
          documentId={openDocumentId}
          folderId={folderId}
          autoOpenShare={autoOpenShare}
          onClose={() => {
            setOpenDocumentId(null);
            setAutoOpenShare(false);
          }}
        />
      )}
    </div>
  );
}

