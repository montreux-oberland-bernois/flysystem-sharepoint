<?php

namespace DelaneyMethod\FlysystemSharepoint;

use Exception;
use GuzzleHttp\Psr7\MimeType;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use DelaneyMethod\Sharepoint\Client;

class SharepointAdapter implements FilesystemAdapter
{
	protected $client;

	public function __construct(Client $client)
	{
		$this->client = $client;
    }
	
	public function write($path, $contents, Config $config): void {
		$this->upload($path, $contents);
	}
	
	public function writeStream($path, $resource, Config $config): void {
		$this->upload($path, $resource);
	}
	
	public function update($path, $contents, Config $config)
	{
		return $this->upload($path, $contents);
	}
	
	public function updateStream($path, $resource, Config $config)
	{
		return $this->upload($path, $resource);
	}
	
	public function read($path): string {
		if (!$response = $this->readStream($path)) {
			return false;
		}
		
		$response['contents'] = stream_get_contents($response['response']);
		
		fclose($response['response']);
		
		unset($response['response']);
		
		return $response;
	}
	
	public function readStream($path)
	{
		$path = $this->applyPathPrefix($path);
		
		$response = $this->client->download($path);
		
		return compact('response');
	}
	
	protected function upload($path, $contents)
	{
		$path = $this->applyPathPrefix($path);
		
		$response = $this->client->upload($path, $contents);
		
		return $this->normalizeResponse($response);
	}
	
	public function rename($path, $newPath) : bool
	{
		$path = $this->applyPathPrefix($path);
		
		$mimeType = $this->getMimetype($path);
		
		$newPath = $this->applyPathPrefix($newPath);
		
		return $this->client->move($path, $newPath, $mimeType);
	}
	
	public function copy($path, $newpath, Config $config) : void {
		$path = $this->applyPathPrefix($path);
		
		$newpath = $this->applyPathPrefix($newpath);
		
		$this->client->copy($path, $newpath, $config->get('mime'));
	}
	
	public function delete($path) : void {
		$path = $this->applyPathPrefix($path);
		
		$this->client->delete($path);
	}
	
	public function deleteDir($dirname) : void
	{
		$this->delete($dirname);
	}
	
	public function createDir($path, Config $config) : bool
	{
		$path = $this->applyPathPrefix($path);
		
		return $this->client->createFolder($path);
	}
	
	public function has($path) : bool
	{
		try {
			$path = $this->applyPathPrefix($path);
			
			$mimeType = $this->getMimetype($path);
			
			$metadata = $this->client->getMetadata($path, $mimeType);

			return !empty($metadata);
		} catch (Exception $exception) {
			return false;
		}
	}
	
	public function listContents($path = '', $recursive = false) : array
	{
		$path = $this->applyPathPrefix($path);
			
		$folders = $this->client->listFolder($path, $recursive);
			
		if (count($folders) === 0) {
			return [];
		}
		
		$allFolders = [];
		
		foreach ($folders as $folder) {
			$object = $this->normalizeResponse($folder);
		
			array_push($allFolders, $object);
			
			if ($recursive) {
				$allFolders = array_merge($allFolders, $this->listContents($folder['Name'], true));
			}
		}
		
		return $allFolders;
	}
	
	public function getMetadata($path)
	{
		$path = $this->applyPathPrefix($path);
		
		$mimeType = $this->getMimetype($path);
		
		$metadata = $this->client->getMetadata($path, $mimeType);
		
		return $this->normalizeResponse($metadata);
	}
	
	public function getSize($path)
	{
		return $this->getMetadata($path);
	}
	
	public function getMimetype($path)
	{
		return ['mimetype' => MimeType::fromFilename($path)];
	}
	
	public function applyPathPrefix($path) : string
	{
		return '/'.trim($path, '/');
	}

	public function getClient() : Client
	{
		return $this->client;
	}
	
	protected function normalizeResponse(array $response) : array
	{
		[, $normalizedPathPart2] = explode('Shared Documents', $response['ServerRelativeUrl']);
		
		$normalizedPath = ltrim($normalizedPathPart2, '/');
	
		$normalizedResponse = [
			'path' => $normalizedPath
		];
		
		if (isset($response['TimeLastModified'])) {
			$normalizedResponse['timestamp'] = strtotime($response['TimeLastModified']);
		} elseif (isset($response['Modified'])) {
			$normalizedResponse['timestamp'] = strtotime($response['Modified']);
		}
		
		if (isset($response['size'])) {
			$normalizedResponse['size'] = $response['size'];
			
			$normalizedResponse['bytes'] = $response['size'];
		}
		
		$type = ($response['__metadata']['type'] === 'SP.Folder' ? 'dir' : 'file');
		
		$normalizedResponse['type'] = $type;
		
		return $normalizedResponse;
	}

    public function fileExists(string $path): bool {
        // TODO: Implement fileExists() method.
    }

    public function deleteDirectory(string $path): void {
        // TODO: Implement deleteDirectory() method.
    }

    public function createDirectory(string $path, Config $config): void {
        // TODO: Implement createDirectory() method.
    }

    public function setVisibility(string $path, string $visibility): void {
        // TODO: Implement setVisibility() method.
    }

    public function visibility(string $path): FileAttributes {
        // TODO: Implement visibility() method.
    }

    public function mimeType(string $path): FileAttributes {
        return new FileAttributes($path, null, null, null, MimeType::fromFilename($path));
    }

    public function lastModified(string $path): FileAttributes {
        // TODO: Implement lastModified() method.
    }

    public function fileSize(string $path): FileAttributes {
        // TODO: Implement fileSize() method.
    }

    public function move(string $source, string $destination, Config $config): void {
        // TODO: Implement move() method.
    }
}
