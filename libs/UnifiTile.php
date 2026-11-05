<?php

declare(strict_types=1);

/**
 * Status tile of the site (HTML-SDK), built like the tiles of the UnifiNet module.
 *
 * The module keeps a state in a buffer, returns it with the initial tile and pushes every change
 * to open visualizations. The state goes to the browser, therefore only fields which are meant to
 * be displayed may be added (never pass an API response as it is).
 */
trait UnifiTile
{
    public function GetVisualizationTile(): string
    {
        // Buffers are empty after a restart of Symcon. With long timer intervals the tile
        // would stay empty for a long time, so the data is requested once here.
        if ($this->GetBuffer('TileState') === '' && $this->GetStatus() === IS_ACTIVE) {
            $this->TileRefresh();
        }
        $state = $this->TileReadState();
        $state['inactive'] = $this->GetStatus() !== IS_ACTIVE;
        $json = json_encode($state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        return str_replace('/*INITIAL_STATE*/null', (string) $json, (string) file_get_contents(__DIR__ . '/tile.html'));
    }

    /** Merges values into the state and sends the complete state to the visualization. */
    private function TileUpdate(array $values): void
    {
        $state = array_replace($this->TileReadState(), $values);
        $state['inactive'] = false;
        $json = (string) json_encode($state, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $this->SetBuffer('TileState', $json);
        $this->UpdateVisualizationValue($json);
    }

    private function TileReadState(): array
    {
        $state = json_decode($this->GetBuffer('TileState'), true);
        return is_array($state) ? $state : [];
    }
}
