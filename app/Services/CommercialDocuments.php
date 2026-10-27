<?php
declare(strict_types=1);
namespace App\Services;
use CodeIgniter\HTTP\Files\UploadedFile;
use DomainException;

final class CommercialDocuments
{
    public function store(?UploadedFile $file): ?array
    {
        if(!$file || $file->getError()===UPLOAD_ERR_NO_FILE) return null;
        if(!$file->isValid() || $file->getSize()>5*1024*1024 || $file->getSize()===0) throw new DomainException('El respaldo debe ser un archivo válido de hasta 5 MB.');
        $mime=$file->getMimeType();$extensions=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
        if(!isset($extensions[$mime])) throw new DomainException('El respaldo debe ser PDF, JPG o PNG.');
        $key=bin2hex(random_bytes(16)).'.'.$extensions[$mime];$dir=WRITEPATH.'uploads/commercial';
        if(!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new \RuntimeException('No se pudo preparar el almacenamiento privado.');
        $name=mb_substr(basename(str_replace('\\','/',$file->getClientName())),0,180);
        $file->move($dir,$key);
        return ['storage_key'=>$key,'original_name'=>$name,'mime_type'=>$mime,'size_bytes'=>filesize($dir.'/'.$key),'sha256'=>hash_file('sha256',$dir.'/'.$key)];
    }
    public function path(string $key): string
    {
        if(!preg_match('/^[a-f0-9]{32}\.(pdf|jpg|png)$/',$key)) throw new DomainException('Documento no disponible.');
        return WRITEPATH.'uploads/commercial/'.$key;
    }
    public function discard(?array $document): void
    {
        if(!$document) return;
        $path=$this->path($document['storage_key']);
        if(is_file($path)) unlink($path);
    }
}
