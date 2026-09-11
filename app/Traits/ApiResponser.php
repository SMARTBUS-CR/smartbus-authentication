<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

trait ApiResponser
{
    /**
     * Return a success response with data and meta information
     * following the [JSON:API](https://jsonapi.org/) specification.
     *
     * @param  array  $meta  Information about the response, such as messages or additional data.
     * @param  int  $status  HTTP status code for the response (default is 200 OK).
     */
    protected function successResponse(array $meta = [], int $status = HttpStatus::HTTP_OK): JsonResponse
    {
        return response()->json([
            'meta' => $meta,
        ], $status);
    }

    /**
     * Return an error response with a detail message, title, and status code
     * following the [JSON:API](https://jsonapi.org/) specification.
     *
     * @param  string  $detail  Detailed error message describing the error.
     * @param  string  $title  Short title for the error (default is 'Error').
     * @param  int  $status  HTTP status code for the error response (default is 400 Bad Request).
     */
    protected function errorResponse(string $detail, string $title = 'Error', int $status = HttpStatus::HTTP_BAD_REQUEST): JsonResponse
    {
        return response()->json([
            'errors' => [
                [
                    'status' => (string) $status,
                    'title' => $title,
                    'detail' => $detail,
                ],
            ],
        ], $status);
    }
}
