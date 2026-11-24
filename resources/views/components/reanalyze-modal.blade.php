<div class="modal fade" id="reanalyzeConfirmModal" tabindex="-1" aria-labelledby="reanalyzeConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="reanalyzeConfirmModalLabel">Re-analyze this post?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <p class="mb-2">
                    This will call the DeepSeek API again and create a new analysis version.
                    The existing analysis history will be preserved.
                </p>
                <p class="small text-muted mb-0" data-reanalyze-title></p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" data-reanalyze-confirm>
                    <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i> Re-analyze
                </button>
            </div>
        </div>
    </div>
</div>
