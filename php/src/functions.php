<?php
/**
 * AKI.IO Model Hub API PHP interface
 *
 * Copyright (c) AKI.IO GmbH and affiliates. Find more info at https://aki.io
 *
 * This software may be used and distributed according to the terms of the MIT LICENSE
 */

namespace AkiIO;

function do_aki_request(
    string $endpointName,
    string $apiKey,
    array $params,
    ?callable $progressCallback = null
): array {
    $aki = new Aki($endpointName, $apiKey);
    return $aki->doApiRequest($params, $progressCallback);
}
