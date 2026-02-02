import Plugin from 'src/plugin-system/plugin.class';

export default class MotivationProgressPlugin extends Plugin {
    init() {
        this.track = this.el.querySelector('.mpb__track');
        this.fill = this.el.querySelector('.mpb__fill');
        this.tooltip = this.el.querySelector('.mpb__tooltip');
        this.tooltipText = this.el.querySelector('.mpb__tooltipText');

        this._apply();
        this._bindMilestones();
        this._watchResize();

        console.log('[MotivationProgress] init', this.el);

    }

    _apply() {
        const progress = parseFloat(this.el.dataset.progress || '0');
        const pct = Math.max(0, Math.min(100, progress));

        // Animate fill (in case inline width is missing later)
        if (this.fill) {
            requestAnimationFrame(() => {
                this.fill.style.width = `${pct}%`;
            });
        }

        // Position tooltip above current progress
        if (this.tooltip && this.track) {
            const trackRect = this.track.getBoundingClientRect();
            const leftPx = (trackRect.width * pct) / 100;

            // Convert px to "left within wrapper"
            this.tooltip.style.left = `${leftPx}px`;
            this.tooltip.style.opacity = '1';
        }
    }

    _bindMilestones() {
        const milestones = this.el.querySelectorAll('[data-mpb-milestone="true"]');
        if (!milestones.length || !this.tooltipText) return;

        milestones.forEach((btn) => {
            btn.addEventListener('mouseenter', () => {
                const label = btn.dataset.label || '';
                this.tooltipText.textContent = label;
            });

            btn.addEventListener('mouseleave', () => {
                // reset to whatever the Twig set initially
                // (keeps it simple for now)
            });
        });
    }

    _watchResize() {
        window.addEventListener('resize', () => this._apply(), { passive: true });
    }
}
