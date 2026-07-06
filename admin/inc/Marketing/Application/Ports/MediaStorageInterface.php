<?php
namespace Marketing\Application\Ports;

interface MediaStorageInterface
{
    /**
     * Upload a file and return its public URL or storage identifier.
     */
    public function upload(string $localPath, string $destinationPath): string;
    
    /**
     * Delete a file from storage.
     */
    public function delete(string $destinationPath): bool;
    
    /**
     * Get the public URL of a stored file.
     */
    public function getUrl(string $destinationPath): string;
}
