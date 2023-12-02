<?php

declare(strict_types=1);

// Copyright 2023 Argo Navis Dev. All rights reserved.
// Use of this source code is governed by a license that can be
// found in the LICENSE file.

namespace ArgoNavis\PhpAnchorSdk\config;

interface ISecretConfig
{
    public function getSep10JwtSecretKey(): ?string;

    public function getSep10SigningSeed(): ?string;

    public function getSep24InteractiveUrlJwtSecret(): ?string;

    public function getSep24MoreInfoUrlJwtSecret(): ?string;

    public function getCallbackAuthSecret(): ?string;

    public function getPlatformAuthSecret(): ?string;

    public function getDataSourceUsername(): ?string;

    public function getDataSourcePassword(): ?string;
}
