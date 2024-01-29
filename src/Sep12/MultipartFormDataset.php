<?php

declare(strict_types=1);

// Copyright 2024 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\Sep12;

use Psr\Http\Message\UploadedFileInterface;

class MultipartFormDataset
{
    /**
     * @var array<string,string> $bodyParams parameters parsed from the body
     */
    public array $bodyParams = [];

    /**
     * @var array<string, UploadedFileInterface> $uploadedFiles uploaded files parsed from the body
     */
    public array $uploadedFiles = [];

    /**
     * @param array<string,string> $bodyParams parameters parsed from the body
     * @param array<string,UploadedFileInterface> $uploadedFiles uploaded files parsed from the body
     */
    public function __construct(array $bodyParams, array $uploadedFiles)
    {
        $this->bodyParams = $bodyParams;
        $this->uploadedFiles = $uploadedFiles;
    }
}
