import Plugin from 'src/plugin-system/plugin.class';

export default class MotivationProgressPlugin extends Plugin {
    init() {
        this.currentTotal = parseFloat(this.el.dataset.currentTotal || '0');
        this.maxGoal = parseFloat(this.el.dataset.maxGoal || '0');
        this.progress = parseFloat(this.el.dataset.progress || '0');

        this.fillEl = this.el.querySelector('.motivation-bar__fill');

        this.tooltipEl = this.el.querySelector('[data-mb-tooltip]');
        this.tooltipTextEl = this.el.querySelector('[data-mb-tooltip-text]');


        this.messageEl =this.el.querySelector('[data-mb-message]');

        try {
            this.goals =JSON.parse(this.el.dataset.goals ||'[]');
        } catch (e) {
            this.goals = [];
        }

        this.locale = this.el.dataset.locale ||'en-US';
        this.currency = this.el.dataset.currency ||'EUR';

        // console.log('MotivationBar data', {
        //     currentTotal: this.currentTotal,
        //     maxGoal: this.maxGoal,
        //     progress: this.progress,
        //     goals: this.goals,
        // });

        this._applyProgress();
        this._updateMessage();
        this._updateMilestones();
        this._bindTooltipEvents();
    }

    _applyProgress() {
        if (!this.fillEl) return;

        const pct = Math.max(0, Math.min(100, this.progress));
        const storageKey = 'mb_last_progress_pct';

        const lastPctRaw = window.sessionStorage.getItem(storageKey);
        const lastPct = lastPctRaw !== null ? parseFloat(lastPctRaw) : null;

        // First time: start at pct (so no 0->pct flash)
        const startPct = Number.isFinite(lastPct) ? Math.max(0, Math.min(100, lastPct)) : pct;

        // Disable transition for the start width so there is no visible jump
        this.fillEl.style.transition = 'none';
        this.fillEl.style.width = `${startPct}%`;

        // Force browser to apply the width immediately
        void this.fillEl.offsetWidth;

        // Re-enable transition, then animate to the new value
        this.fillEl.style.transition = ''; // uses CSS transition again
        requestAnimationFrame(() => {
            this.fillEl.style.width = `${pct}%`;
        });

        // Save for next time
        window.sessionStorage.setItem(storageKey, String(pct));
    }

    _formatMoney(value) {
        return new Intl.NumberFormat(this.locale, {
            style:'currency',
            currency:this.currency,
        }).format(value);
    }

    _updateMessage() {
        if (!this.messageEl)return;

        // Make sure goals are sorted ascending by amount
        const goalsSorted = [...this.goals].sort((a, b) => a.amount - b.amount);

        // Find the first goal that is not reached yet
        const nextGoal = goalsSorted.find(g => this.currentTotal < g.amount);

        if (!nextGoal) {
            const labels = goalsSorted.map((g) => g.label);

            let rewardText = '';

            if (labels.length === 1) {
                rewardText = labels[0];
            } else if (labels.length === 2) {
                rewardText = `${labels[0]} and ${labels[1]}`;
            } else if (labels.length > 2) {
                rewardText = `${labels.slice(0, -1).join(', ')} and ${labels.at(-1)}`;
            }

            this.messageEl.textContent = `Nice! You have ${rewardText} 🎉`;
            return;
        }

        const remaining = nextGoal.amount - this.currentTotal;

        this.messageEl.textContent =`You only need ${this._formatMoney(remaining)} for ${nextGoal.label}!`;
    }

    _updateMilestones() {
        const goalEls = this.el.querySelectorAll('[data-mb-goal]');
        goalEls.forEach((btn) => {
            const amount = parseFloat(btn.dataset.goalAmount ||'0');
            const reached = this.currentTotal >= amount;

            btn.classList.toggle('is-reached', reached);
        });
    }

    _bindTooltipEvents() {
        const goalEls = this.el.querySelectorAll('[data-mb-goal]');

        if (!this.tooltipEl || !this.tooltipTextEl || !goalEls.length) return;

        // Last

        goalEls.forEach((btn) => {
            console.log(btn);

            btn.addEventListener('mouseenter', () => {
                this.tooltipTextEl.textContent = btn.dataset.goalLabel || '';

                if (btn.classList.contains("is-reached")) {
                    this.tooltipEl.hidden = false;
                }

                const wrapper = this.el.querySelector('.motivation-bar__track-wrapper');
                const wrapperRect = wrapper.getBoundingClientRect();
                const btnRect = btn.getBoundingClientRect();

                // dot center inside wrapper (px)
                const dotCenterX = (btnRect.left + btnRect.width / 2) - wrapperRect.left;

                // measure tooltip width (must be visible to measure)
                const tooltipWidth = this.tooltipEl.offsetWidth;
                const half = tooltipWidth / 2;

                // clamp tooltip center so bubble stays inside wrapper
                const clampedCenterX = Math.max(half, Math.min(wrapperRect.width - half, dotCenterX));

                // set tooltip bubble position
                this.tooltipEl.style.left = `${clampedCenterX}px`;

                // now compute arrow position inside tooltip so it still points to the dot
                // tooltip left is the CENTER (because we translateX(-50%))
                // tooltip left edge in wrapper coords:
                const tooltipLeftEdgeX = clampedCenterX - half;

                // arrow x inside tooltip box (px from left)
                let arrowX = dotCenterX - tooltipLeftEdgeX;

                // keep arrow inside some padding so it doesn't go outside rounded corners
                const padding = 7;
                arrowX = Math.max(padding, Math.min(tooltipWidth - padding, arrowX));

                // pass it to CSS variable
                this.tooltipEl.style.setProperty('--arrow-x', `${arrowX}px`);

                // show animation
                this.tooltipEl.classList.add('is-visible');

            });

            btn.addEventListener('mouseleave', () => {
                this.tooltipEl.classList.remove('is-visible');
                this.tooltipEl.hidden = true;
            });

        });
    }


}
