<?php

$jsonContent = json_decode(file_get_contents("config/modules.json"), true);
return $jsonContent['modules'];