<?php

namespace App\Http\Controllers;

use App\Models\Request as ApiRequest;
use App\Models\RequestFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Uploads backing the file rows of a request's multipart/form-data body.
 *
 * A file is stored the moment it's picked, before the request itself is saved —
 * the body JSON only carries the resulting id, so saving the request is what
 * actually commits the field to the body. That means an upload the user then
 * abandons stays on disk until the request or its folder is deleted.
 */
class RequestFileController extends Controller
{
    public function store(Request $request, ApiRequest $apiRequest): JsonResponse
    {
        $this->authorize('edit', $apiRequest->collection->workspace);

        $request->validate([
            'file' => ['required', 'file', 'max:'.config('requests.max_upload_kilobytes')],
        ]);

        $upload = $request->file('file');

        $file = $apiRequest->files()->create([
            // Never the client-supplied name on disk — that's display-only, kept in
            // `filename` for the editor and for the multipart part we later send.
            // Laravel hashes its own name here, so a "../" in the original can't
            // steer where this lands.
            'path' => $upload->store('request-files/'.$apiRequest->id, RequestFile::DISK),
            'filename' => $upload->getClientOriginalName(),
            'mime_type' => $upload->getClientMimeType(),
            'size' => $upload->getSize(),
        ]);

        return response()->json($file, 201);
    }

    /**
     * Streams the stored file back. Needed because requests aimed at .test/.local
     * hosts are fired by the browser rather than this server (see localRequest.ts),
     * and the browser has to re-attach the file to its own FormData.
     */
    public function show(RequestFile $requestFile): StreamedResponse
    {
        $this->authorize('view', $requestFile->request->collection->workspace);

        return Storage::disk(RequestFile::DISK)->download(
            $requestFile->path,
            $requestFile->filename,
            ['Content-Type' => $requestFile->mime_type ?? 'application/octet-stream'],
        );
    }

    public function destroy(RequestFile $requestFile): JsonResponse
    {
        $this->authorize('edit', $requestFile->request->collection->workspace);

        $requestFile->delete();

        return response()->json(status: 204);
    }
}
