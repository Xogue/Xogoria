<?php

final class ClipReviewManager {
    public function __construct(
        private ClipManager $clips,
        private TwitchClipService $twitch,
        private ClipDeletionRegistry $deletions
    ) { }

    public function list(): array {
        $stored = $this->clips->getAllClipInfo();
        $warning = null;
        try { $recent = $this->twitch->recent(); }
        catch (Throwable $error) { $recent = []; $warning = $error->getMessage(); }

        $merged = $stored;
        foreach ($recent as $clip) {
            $id = $clip['id'];
            $merged[$id] = array_merge([
                'customTitle' => null, 'favorite' => false, 'enabled' => false, 'playCount' => 0,
                'maxDuration' => 0, 'startOffset' => 0, 'reviewStatus' => 0, 'localUrl' => null,
                'audioNormalized' => false,
            ], $stored[$id] ?? [], $clip);
        }
        return ['clips' => array_values($merged), 'deletions' => array_values($this->deletions->all()), 'warning' => $warning];
    }

    public function approve(string $clipId, array $clip): bool { return $this->clips->approve($clipId, $clip); }
    public function ignore(string $clipId): bool { return $this->clips->setReviewStatus($clipId, 2); }
    public function save(string $clipId, array $data): bool { return $this->clips->saveMetadata($clipId, $data); }

    public function requestDeletion(string $clipId, string $requestedBy): array {
        $stored = $this->clips->getAllClipInfo()[$clipId] ?? [];
        if (!$this->clips->setReviewStatus($clipId, 3)) { throw new RuntimeException('Unable to mark clip for deletion'); }
        return $this->deletions->record($clipId, $this->twitch->twitchUrl($clipId), $stored['localUrl'] ?? null, $requestedBy);
    }
}
