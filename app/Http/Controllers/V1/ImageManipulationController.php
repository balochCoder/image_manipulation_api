<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResizeImageRequest;
use App\Http\Resources\V1\ImageManipulationResource;
use App\Models\Album;
use App\Models\ImageManipulation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;


class ImageManipulationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return ImageManipulationResource::collection(ImageManipulation::paginate());
    }

    public function byAlbum(Album $album)
    {
        $where = [
            'album_id' => $album->id
        ];

        return ImageManipulationResource::collection(ImageManipulation::where($where)->paginate());
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreImageManipulationRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function resize(ResizeImageRequest $request)
    {
        $all = $request->all();

        $image = $all['image'];
        unset($all['image']);
        $data = [
            'type' => ImageManipulation::TYPE_RESIZE,
            'data' => json_encode($all),
            'user_id' => null,
        ];

        if (isset($all['album_id'])) {
            //TODO            
            $data['album_id'] = $all['album_id'];
        }
        $dir = 'images/' . Str::random() . '/';
        $absolutePath = public_path($dir);
        File::makeDirectory($absolutePath);



        if ($image instanceof UploadedFile) {
            $data['name'] = $image->getClientOriginalName();

            $filename = pathinfo($data['name'], PATHINFO_FILENAME);
            $extension = $image->getClientOriginalExtension();

            $originalPath = $absolutePath . $data['name'];

            $image->move($absolutePath, $data['name']);

            $data['path'] = $dir . $data['name'];
        } else {
            $data['name'] = pathinfo($image, PATHINFO_BASENAME);
            $filename = pathinfo($data['name'], PATHINFO_FILENAME);
            $extension = pathinfo($data['name'], PATHINFO_EXTENSION);
            $originalPath = $absolutePath . $data['name'];


            copy($image, $originalPath);
        }
        $data['path'] = $dir . $data['name'];

        $w = $all['w'];
        $h = $all['h'] ?? false;


        list($width, $height,$image) = $this->getImageWidthAndHeight($w, $h, $originalPath);

       $resizedFilename = $filename.'-resized.'.$extension;

       $image->resize($width,$height)->save($absolutePath.$resizedFilename);

       $data['output_path'] = $dir.$resizedFilename;


       $imageManipulation = ImageManipulation::create($data);

       return new ImageManipulationResource($imageManipulation);

    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ImageManipulation  $imageManipulation
     * @return \Illuminate\Http\Response
     */
    public function show(ImageManipulation $image)
    {
        return new ImageManipulationResource($image);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ImageManipulation  $imageManipulation
     * @return \Illuminate\Http\Response
     */
    public function destroy(ImageManipulation $image)
    {
        $image->delete();

        return response('',204);
    }

    protected function getImageWidthAndHeight($w, $h, $originalPath)
    {
        $image = Image::make($originalPath);
        $orignalWidth = $image->width();
        $originalHeight = $image->height();


        if (str_ends_with($w, '%')) {
            $rationW = (float)Str::replace('%', '', $w);
            $ratioH = $h ? (float)Str::replace('%', '', $h) : $rationW;

            $newWidth = $orignalWidth * $rationW / 100;
            $newHeight = $originalHeight * $ratioH / 100;
        } else {
            $newWidth = (float)$w;


            $newHeight = $h ? (float)$h : $originalHeight * $newWidth / $orignalWidth;
        }

        return [$newWidth, $newHeight,$image];
    }
}
