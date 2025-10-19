<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\Info(
 *     title="Rekaz Simple Drive API",
 *     version="1.0.0",
 *     description="A simple object storage system providing a single interface for multiple storage backends including S3 Compatible Storage, Database, Local File System, and FTP.",
 *     @OA\Contact(
 *         email="admin@rekaz.com",
 *         name="Rekaz Team"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://rekaz.test",
 *     description="Local Development Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter token in format: Bearer {token}"
 * )
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}