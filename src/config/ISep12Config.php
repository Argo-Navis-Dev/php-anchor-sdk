<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\config;

interface ISep12Config
{
    /**
     * @return int|null the maximum size of a file to be uploaded. If not set, it defaults to 2 MB.
     */
    public function getUploadFileMaxSizeMb(): ?int;

    /**
     * @return int|null the maximum number of allowed files to be uploaded. If not set, it defaults to 6.
     */
    public function getUploadFileMaxCount(): ?int;
}
