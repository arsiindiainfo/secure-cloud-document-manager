/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Clock, FileText, FolderOpen, FolderPlus, Pencil, Share2, Trash2, Upload, Users2 } from 'lucide-react';
import { Breadcrumbs } from '../../components/Breadcrumbs';
import { StatCard } from '../../components/StatCard';
import { UploadDropzone } from '../../components/UploadDropzone';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { DocumentThumbnail } from '../../components/DocumentThumbnail';
import { apiErrorMessage } from '../../lib/apiError';
import { folderColorFor } from '../../lib/folderColors';
import { relativeDay } from '../../lib/relativeTime';
import { useAuth } from '../auth/useAuth';
import { fetchDashboardSummary } from '../dashboard/api';
import { useFolderChildren, useCreateFolder, useDeleteFolder, useRenameFolder, useDeleteDocumentInFolder } from './hooks';
import { CreateFolderDialog } from './CreateFolderDialog';
import { RenameFolderDialog } from './RenameFolderDialog';
import { UploadDialog } from '../documents/UploadDialog';
import { DocumentDetailPanel } from '../documents/DocumentDetailPanel';
import { ShareDialogLoader } from '../sharing/ShareDialogLoader';
import type { Document, Folder, RecentDocumentActivity } from '../../types/api';

function activityLabel(action: RecentDocumentActivity['action']): string {
  switch (action) {
    case 'DOCUMENT_DOWNLOADED':
      return 'Downloaded';
    case 'DOCUMENT_VERSION_UPLOADED':
      return 'New version uploaded';
    default:
      return action;
  }
}

export function FolderBrowserPage() {
  const { folderId: folderIdParam } = useParams<{ folderId?: string }>();
  const folderId = folderIdParam ? Number(folderIdParam) : null;
  const isRoot = folderId === null;
  const navigate = useNavigate();
  const { user } = useAuth();

  const { data, isLoading, isError } = useFolderChildren(folderId);
  const { data: dashboard } = useQuery({
    queryKey: ['dashboard'],
    queryFn: fetchDashboardSummary,
    enabled: isRoot,
  });
  const createFolder = useCreateFolder(folderId);
  const deleteFolder = useDeleteFolder(folderId);
  const renameFolder = useRenameFolder(folderId);
  const deleteDocument = useDeleteDocumentInFolder(folderId);

  const [showCreateFolder, setShowCreateFolder] = useState(false);
  const [pendingUploadFiles, setPendingUploadFiles] = useState<File[] | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<Folder | null>(null);
  const [renameTarget, setRenameTarget] = useState<Folder | null>(null);
  const [openDocumentId, setOpenDocumentId] = useState<number | null>(null);
  const [shareDocumentId, setShareDocumentId] = useState<number | null>(null);
  const [deleteDocumentTarget, setDeleteDocumentTarget] = useState<Document | null>(null);
  const [documentRowError, setDocumentRowError] = useState<{ id: number; message: string } | null>(null);

  function openFolder(id: number) {
    navigate(`/browse/${id}`);
  }

  async function handleDeleteDocument() {
    if (!deleteDocumentTarget) return;
    const target = deleteDocumentTarget;
    try {
      await deleteDocument.mutateAsync(target.id);
      setDeleteDocumentTarget(null);
    } catch (err) {
      setDocumentRowError({ id: target.id, message: apiErrorMessage(err, 'Could not delete document') });
      setDeleteDocumentTarget(null);
    }
  }

  function handleFilesSelected(files: File[]) {
    if (folderId === null) return; // can't upload at the very root — pick a folder first
    setPendingUploadFiles(files);
  }

  const folders = data?.folders ?? [];
  const documents = data?.documents ?? [];
  const isEmpty = !isLoading && folders.length === 0 && documents.length === 0;
  const recentDocuments = dashboard?.recentDocuments ?? [];

  return (
    <div>
      {!isRoot && (
        <div className="mb-4">
          <Breadcrumbs entries={data?.breadcrumb ?? []} />
        </div>
      )}

      {isRoot && (
        <>
          <div className="mb-6 flex items-center gap-4 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 p-6 text-white">
            <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-white/15">
              <FolderOpen size={26} />
            </div>
            <div>
              <h1 className="text-xl font-semibold">Welcome back, {user?.name}</h1>
              <p className="text-sm text-blue-100">Access and manage your documents securely in the cloud.</p>
            </div>
          </div>

          <div className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <StatCard
              icon={FolderOpen}
              iconClassName="bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-400"
              label="Total Folders"
              value={folders.length}
            />
            <StatCard
              icon={FileText}
              iconClassName="bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400"
              label="Total Files"
              value={dashboard?.quotas.files.used ?? 0}
            />
            <StatCard
              icon={Users2}
              iconClassName="bg-purple-100 text-purple-600 dark:bg-purple-950 dark:text-purple-400"
              label="Shared With Me"
              value={dashboard?.sharedFolders.length ?? 0}
              to="/dashboard"
            />
            <StatCard
              icon={Clock}
              iconClassName="bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-400"
              label="Recent Activity"
              value={recentDocuments[0] ? relativeDay(recentDocuments[0].at) : '—'}
            />
          </div>
        </>
      )}

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <section>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
              <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">
                {isRoot ? 'My Folders' : (data?.breadcrumb.at(-1)?.name ?? 'Home')}
              </h2>
              <div className="flex gap-2">
                <button
                  onClick={() => setShowCreateFolder(true)}
                  className="flex items-center gap-1.5 rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                  <FolderPlus size={15} /> New folder
                </button>
                {!isRoot && (
                  <label className="flex cursor-pointer items-center gap-1.5 rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                    <Upload size={15} /> Upload
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
                <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-slate-300 py-16 text-center dark:border-slate-700">
                  <p className="text-sm text-slate-500 dark:text-slate-400">
                    {isRoot ? 'No folders yet — create your first one to get started' : 'This folder is empty'}
                  </p>
                  {!isRoot && (
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

              {!isLoading && !isError && !isEmpty && isRoot && (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                  {folders.map((folder) => (
                    <div
                      key={folder.id}
                      onClick={() => openFolder(folder.id)}
                      className="group relative cursor-pointer rounded-xl border border-slate-200 bg-white p-4 transition-colors hover:border-slate-300 hover:shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:hover:border-slate-600"
                    >
                      <div className={`mb-3 flex h-11 w-11 items-center justify-center rounded-lg ${folderColorFor(folder.id)}`}>
                        <FolderOpen size={20} />
                      </div>
                      <p className="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{folder.name}</p>
                      <p className="text-xs text-slate-400 dark:text-slate-500">
                        {folder.itemCount} item{folder.itemCount === 1 ? '' : 's'}
                      </p>
                      <div className="absolute right-2 top-2 hidden gap-1 group-hover:flex">
                        <button
                          onClick={(e) => {
                            e.stopPropagation();
                            setRenameTarget(folder);
                          }}
                          aria-label="Rename folder"
                          className="rounded-full bg-white p-1.5 text-slate-500 shadow hover:text-slate-700 dark:bg-slate-700 dark:text-slate-300"
                        >
                          <Pencil size={13} />
                        </button>
                        <button
                          onClick={(e) => {
                            e.stopPropagation();
                            setDeleteTarget(folder);
                          }}
                          aria-label="Delete folder"
                          className="rounded-full bg-white p-1.5 text-red-500 shadow hover:text-red-700 dark:bg-slate-700"
                        >
                          <Trash2 size={13} />
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              )}

              {!isLoading && !isError && !isEmpty && !isRoot && (
                <ul className="divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-800">
                  {folders.map((folder) => (
                    <li
                      key={`folder-${folder.id}`}
                      className="flex cursor-pointer items-center justify-between px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/50"
                      onClick={() => openFolder(folder.id)}
                    >
                      <span className="flex items-center gap-2 text-sm text-slate-800 dark:text-slate-200">
                        <span className={`flex h-7 w-7 items-center justify-center rounded-md ${folderColorFor(folder.id)}`}>
                          <FolderOpen size={14} />
                        </span>
                        {folder.name}
                        <span className="text-xs text-slate-400">
                          {folder.itemCount} item{folder.itemCount === 1 ? '' : 's'}
                        </span>
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
                      <div>
                        <span className="flex items-center gap-2 text-sm text-slate-800 dark:text-slate-200">
                          <DocumentThumbnail documentId={doc.id} hasThumbnail={doc.hasThumbnail} mimeType={doc.mimeType} /> {doc.name}
                          <span className="text-xs text-slate-400">v{doc.currentVersion}</span>
                        </span>
                        {documentRowError?.id === doc.id && <p className="mt-1 text-xs text-red-600">{documentRowError.message}</p>}
                      </div>
                      <span className="flex gap-3">
                        <button
                          onClick={(e) => {
                            e.stopPropagation();
                            setShareDocumentId(doc.id);
                          }}
                          className="text-xs text-slate-500 hover:underline dark:text-slate-400"
                        >
                          Share
                        </button>
                        <button
                          onClick={(e) => {
                            e.stopPropagation();
                            setDocumentRowError(null);
                            setDeleteDocumentTarget(doc);
                          }}
                          className="text-xs text-red-600 hover:underline"
                        >
                          Delete
                        </button>
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </UploadDropzone>
          </section>

          {isRoot && (
            <section>
              <h2 className="mb-3 text-base font-semibold text-slate-900 dark:text-slate-100">Recent Documents</h2>
              {recentDocuments.length === 0 ? (
                <p className="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                  No recent activity.
                </p>
              ) : (
                <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-400 dark:border-slate-700">
                        <th className="px-4 py-2 font-medium">Name</th>
                        <th className="px-4 py-2 font-medium">Activity</th>
                        <th className="px-4 py-2 font-medium">When</th>
                      </tr>
                    </thead>
                    <tbody>
                      {recentDocuments.map((activity) => (
                        <tr
                          key={`${activity.documentId}-${activity.at}`}
                          className="cursor-pointer border-t border-slate-100 first:border-t-0 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-700/50"
                          onClick={() => navigate(`/browse/${activity.folderId}`)}
                        >
                          <td className="px-4 py-3 font-medium text-slate-800 dark:text-slate-200">{activity.name}</td>
                          <td className="px-4 py-3 text-slate-500 dark:text-slate-400">{activityLabel(activity.action)}</td>
                          <td className="px-4 py-3 text-slate-500 dark:text-slate-400">{relativeDay(activity.at)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </section>
          )}
        </div>

        {isRoot && (
          <div className="space-y-6">
            <section className="rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 p-5 text-white">
              <h3 className="mb-1 font-semibold">Secure Cloud Storage</h3>
              <p className="text-sm text-indigo-100">Your files are encrypted, versioned, and backed up automatically.</p>
            </section>

            <section className="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
              <h3 className="mb-3 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Quick Actions</h3>
              <div className="space-y-1">
                <button
                  onClick={() => setShowCreateFolder(true)}
                  className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50"
                >
                  <FolderPlus size={16} /> New Folder
                </button>
                <Link
                  to="/trash"
                  className="flex items-center gap-2 rounded-md px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50"
                >
                  <Trash2 size={16} /> View Trash
                </Link>
                <Link
                  to="/dashboard"
                  className="flex items-center gap-2 rounded-md px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50"
                >
                  <Share2 size={16} /> Shared With Me
                </Link>
              </div>
            </section>
          </div>
        )}
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

      {deleteDocumentTarget && (
        <ConfirmDialog
          title={`Delete "${deleteDocumentTarget.name}"?`}
          message="Moves to Trash — can be restored within 30 days."
          confirmLabel="Delete"
          onConfirm={handleDeleteDocument}
          onCancel={() => setDeleteDocumentTarget(null)}
        />
      )}

      {openDocumentId !== null && folderId !== null && (
        <DocumentDetailPanel documentId={openDocumentId} folderId={folderId} onClose={() => setOpenDocumentId(null)} />
      )}

      {shareDocumentId !== null && (
        <ShareDialogLoader documentId={shareDocumentId} onClose={() => setShareDocumentId(null)} />
      )}
    </div>
  );
}
