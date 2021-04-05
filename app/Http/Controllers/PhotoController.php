<?php

namespace App\Http\Controllers;

use App\Photo;
use App\Album;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use function photon_image_process;

class PhotoController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $user = auth()->user();
        $data["photos"] = Photo::query()->latest()->paginate(10);
        return view('backend.photo.index', $data);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $data["albums"] = Album::all();
        return view('backend.photo.create', $data);
    }

    public function store(Request $request)
    {

        try {
            $validation = Validator::make($request->all(), [
                'title' => 'required|string',
                'image' => 'required|image'
            ]);

            if ($validation->fails()) {
                throw new Exception($validation->getMessageBag());
            }

            if(!$request->hasFile('image')){
                throw new Exception("bukan file");
            }

            $fileName = str_slug($request->input('title')) . '.' . $request->file('image')->getClientOriginalExtension();

            Log::debug("fileName : $fileName");

            $album = Album::query()->findOrFail($request->input('album_id'));

            $filePath = "{$album->slug}/asset/$fileName";

            Log::debug("filepath : $filePath");

            Log::debug(Storage::disk()->exists($filePath));

            if (Storage::disk()->exists($filePath)) {
                throw new Exception("File sudah ada");
            }

            DB::beginTransaction();

            Photo::query()->create([
                'title' => $request->input('title'),
                'slug' => str_slug($request->title),
                'description' => $request->input('description'),
                'image' => "https://5p4c3.sgp1.cdn.digitaloceanspaces.com/$filePath",
                'user_id' => Auth::id(),
                'album_id' => $request->input('album_id')
            ]);

            if (!Storage::disk()->put($filePath, file_get_contents($request->file('image')))) {
                throw new Exception("Gagal Upload file");
            }

            DB::commit();

            return redirect()->route('photo.index')->with('status', 'Album successfully created');

        } catch (Exception $exception) {
            Log::debug($exception);
            return redirect()->back()->withErrors($exception->getMessage());
        }

    }

    public function show(Photo $photo)
    {

        $data["photo"] = $photo;
        return view('backend.photo.show', $data);

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Photo $gallery
     * @return Response
     */
    public function edit(Photo $photo)
    {
        $data["albums"] = Album::all();
        $data["photo"] = $photo;
        return view('backend.photo.edit', $data);

    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param Photo $gallery
     * @return Response
     */
    public function update(Request $request, Photo $gallery)
    {
        $validation = $request->validate([
            'title' => 'required|string',
            'description' => 'required|string',
            'title' => 'required|string',
        ]);


        $imgName = photon_image_process($request, "thumbnail");

        if ($request->hasFile("thumbnail")) {
            $service->update([
                'title' => $request->title,
                'slug' => str_slug($request->title),
                'description' => $request->description,
                'album_id' => $request->album_id
            ]);

        } else {
            $service->update([
                'title' => $request->title,
                'slug' => str_slug($request->title),
                'price' => $request->price,
                'description' => $request->description,
                'image' => $imgName,
                'album_id' => $request->album_id
            ]);

        }

        session()->flash('type', 'success');
        session()->flash('message', 'Service Successfully Updated');
        return redirect()->route('service.index');


    }

    public function destroy($id)
    {
        try {

            $photo = Photo::query()->findOrFail($id);

            Log::debug($photo);

            $filePath = str_replace('https://5p4c3.sgp1.cdn.digitaloceanspaces.com/', '', $photo->image);

            Log::debug($filePath);

            Log::debug(Storage::disk()->exists($filePath));

            if (!Storage::disk()->exists($filePath)) {
                throw new Exception("File Tidak ditemukan");
            }

            if (!Storage::disk()->delete($filePath)) {
                throw new Exception("Gagal hapus file");
            }

            $photo->delete();

            return redirect()->route('photo.index')->with('status', 'Album successfully deleted');

        } catch (Exception $exception) {
            Log::debug($exception);
            return redirect()->back()->withErrors($exception->getMessage());
        }
    }
}
