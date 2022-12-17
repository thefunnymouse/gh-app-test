<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DemoController extends Controller
{
    private $REPO;
    private $TOKEN;

    public function __construct()
    {
        $this->REPO = env("REPO");
        $this->TOKEN = env("TOKEN");
    }

    public function upload(Request $request)
    {
        $files = $request->file("files");

        $branchName = "main";
        $baseTreeSha = $this->getBaseTreeSha($branchName);

        $blobs = $this->createBlobs($files);

        $treeSha = $this->createTrees($blobs, $baseTreeSha);

        $commitSha = $this->createCommit($treeSha, $baseTreeSha);

        return $this->updateRefs($commitSha, $branchName);
    }

    function getBaseTreeSha($branchName, $directory = "")
    {
        $result = $this->submitGithub("/git/trees/" . $branchName, "GET", null);
        return $result["response"]->sha;
    }

    function createBlobs($files)
    {
        if (!is_array($files)) {
            Log::error("Invalid param");
            return null;
        }

        return array_map(function ($file) {
            return $this->createBlob($file);
        }, $files);
    }

    function createBlob($file)
    {
        Log::info("Read file content: " . $file->getRealPath());
        $content = file_get_contents($file->getRealPath());

        // using raw text
//        $body = [
//            "content" => $content,
//            "encoding" => "utf-8"
//        ];

        // using base64
        $body = [
            "content" => base64_encode($content),
            "encoding" => "base64"
        ];

        $result = $this->submitGithub("/git/blobs", "POST", $body);
        Log::info("Blob creation result: " . json_encode($result));

        if ($result["status"] === 201) {
            return [
                "sha" => $result["response"]->sha,
                "filename" => $file->getClientOriginalName()
            ];
        }

        Log::error("Could not create blob");

        return null;
    }

    function createTrees($blobs, $baseTreeSha)
    {
        $trees = array_map(function ($blob) {
            return [
                "path" => $blob["filename"],
                "mode" => "100644", // default
                "type" => "blob", //default,
                "sha" => $blob["sha"]
            ];
        }, $blobs);

        $body = [
            "base_tree" => $baseTreeSha,
            "tree" => $trees
        ];

        $result = $this->submitGithub("/git/trees", "POST", $body);
        if ($result["status"] === 201) {
            return $result["response"]->sha;
        }

        Log::error("Failed to create trees: " . json_encode($result));
        return null;
    }

    function createCommit($treeSha, $parentTreeSha)
    {
        $body = [
            "message" => "Backup " . date("Y-m-d_H:i:s"),
            "author" => ["name" => "Backend", "email" => "backend@demo.github.com"],
            "parents" => [$parentTreeSha],
            "tree" => $treeSha
        ];

        $result = $this->submitGithub("/git/commits", "POST", $body);
        if ($result["status"] === 201) {
            return $result["response"]->sha;
        }

        Log::error("Failed to create commit: " . json_encode($result));
        return null;
    }

    function updateRefs($commitSha, $branchName)
    {
        $body = ["sha" => $commitSha];
        return $this->submitGithub("/git/refs/heads/" . $branchName, "PATCH", $body);
    }

    function submitGithub($apiPath, $method, $body)
    {
        $url = $this->REPO . $apiPath;

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => array(
                'Accept: application/vnd.github+json',
                'X-GitHub-Api-Version: 2022-11-28',
                'Authorization: Bearer ' . $this->TOKEN,
                'Content-Type: application/json',
                "User-Agent: Backend Server"
            ),
        ));

        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        Log::info($apiPath . ": Response " . $response);

        return [
            "status" => $status,
            "response" => json_decode($response)
        ];
    }
}
