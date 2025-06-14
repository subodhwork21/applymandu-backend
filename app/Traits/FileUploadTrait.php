<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait FileUploadTrait
{

    public function uploadEmployerImage(UploadedFile $image, string $folder = 'uploads', string $disk = 'public', string $filename = null): string
    {
        $folder = 'employer';
        $filename = 'employer_' . time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();

        return $this->uploadFileToStorage($image, $folder, 'public', $filename);
    }


    public function uploadFileToStorage(UploadedFile $image, string $folder = 'uploads', string $disk = 'public', string $filename = null): string
    {
        $filename = $filename ?? Str::random(20) . '.' . $image->getClientOriginalExtension();
        $path = $image->storeAs($folder, $filename, $disk);
    
        return $path; 
    }



    public function uploadJobSeekerImage(UploadedFile $image, string $folder = 'uploads', string $disk = 'public', string $filename = null): string
    {
        $folder = 'jobseeker';
        $filename = 'jobseeker_' . time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();

        ///delete old image if exists
        // $this->deleteFileFromStorage($this->getJobSeekerImagePath($this->image));

        return $this->uploadFileToStorage($image, $folder, 'public', $filename);
    }


    public function uploadEmployerLogo(UploadedFile $image, string $folder = 'uploads', string $disk = 'public', string $filename = null): string
    {
        $folder = 'employer';
        $filename = 'employer_' . time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();

        //delete old image if exists
        // $this->deleteFileFromStorage($this->getEmployerLogoPath($this->image));

        return $this->uploadFileToStorage($image, $folder, 'public', $filename);
    }
}
