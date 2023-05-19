<?php

namespace Templates;

/**
 * @deprecated
 */
interface TemplateProcessor {

    /**
     * Processes bodies replacing any placeholders there with the data retrieved by using id of an entity.
     * @param int|string $entityId
     * @param string|array $bodies
     * @param array $additionalData
     * @param string $templateType
     * @return string|array
     */
    public function processTemplate($entityId, $bodies, $additionalData = [], $templateType = 'default');

}