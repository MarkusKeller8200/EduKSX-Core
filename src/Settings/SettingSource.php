<?php

namespace EduKSX\Core\Settings;

/** Herkunft des geltenden Werts einer Einstellung. */
enum SettingSource: string
{
    /** Umgebungsvariable gesetzt – in der Administration gesperrt. */
    case Environment = 'environment';
    /** In der Administration abweichend von der Vorgabe gespeichert. */
    case Administration = 'administration';
    /** Vorgabe aus Code bzw. Konfiguration. */
    case Default = 'default';

    public function label(): string
    {
        return (string) __('eduksx::settings.source.'.$this->value);
    }
}
