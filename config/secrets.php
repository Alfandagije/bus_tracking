<?php
function loadSecrets() {
    $secretPaths = [
        '/etc/secrets/env.json',
        __DIR__ . '/../env.json',
        __DIR__ . '/../.env.json',
    ];

    foreach ($secretPaths as $path) {
        if (file_exists($path)) {
            $data = json_decode(file_get_contents($path), true);
            if ($data && is_array($data)) {
                foreach ($data as $key => $value) {
                    if (!getenv($key)) {
                        putenv("$key=$value");
                        $_ENV[$key] = $value;
                    }
                }
            }
            return;
        }
    }
}
