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


        this.modalOverlayEl =this.el.querySelector('[data-mb-modal-overlay]');
        this.modalEl =this.el.querySelector('[data-mb-modal]');
        this.modalCloseEl =this.el.querySelector('[data-mb-modal-close]');
        this.modalTitleEl =this.el.querySelector('[data-mb-modal-title]');
        this.modalBodyEl =this.el.querySelector('[data-mb-modal-body]');
        this.modalIconEl =this.el.querySelector('[data-mb-modal-icon]');

        this.modalStatusEl =this.el.querySelector('[data-mb-modal-status]');

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
        this._bindModalEvents();
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
            const amount = parseFloat(btn.dataset.goalAmount || '0');
            const reachedNow = this.currentTotal >= amount;

            const wasReached = btn.classList.contains('is-reached');

            btn.classList.toggle('is-reached', reachedNow);

            // Accessibility + behavior
            btn.setAttribute('aria-disabled', reachedNow ? 'false' : 'true');
            btn.tabIndex = reachedNow ? 0 : -1;

            // Trigger pop ONLY when it just became reached
            if (!wasReached && reachedNow) {
                const dot = btn.querySelector('.motivation-bar__goalDot');
                if (dot) {
                    dot.classList.remove('is-pop'); // reset if needed
                    // force reflow so re-adding class restarts animation
                    void dot.offsetWidth;
                    dot.classList.add('is-pop');

                    dot.addEventListener('animationend', () => {
                        dot.classList.remove('is-pop');
                    }, { once: true });

                }
            }
        });
    }

    _bindTooltipEvents() {
        const goalEls = this.el.querySelectorAll('[data-mb-goal]');

        if (!this.tooltipEl || !this.tooltipTextEl || !goalEls.length) return;

        goalEls.forEach((btn) => {
            console.log(btn);

            btn.addEventListener('mouseenter', () => {
                this.tooltipTextEl.textContent = btn.dataset.goalLabel || '';

                this.tooltipEl.hidden = false;

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

    _bindModalEvents() {
        const goalEls =this.el.querySelectorAll('[data-mb-goal]');
        if (!this.modalOverlayEl || !this.modalEl || !goalEls.length)return;

        // Open on goal click
        goalEls.forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();

                // Only open if reached
                if (btn.getAttribute('aria-disabled') === 'true') return;

                this._openModalFromGoal(btn);
            });
        });

        // Close via X button
        if (this.modalCloseEl) {
            this.modalCloseEl.addEventListener('click',(e) => {
                e.preventDefault();
                this._closeModal();
            });
        }

        // Close by clicking outside modal (on overlay)
        this.modalOverlayEl.addEventListener('click',(e) => {
            if (e.target ===this.modalOverlayEl) {
                this._closeModal();
            }
        });

        // Close on Esc
        document.addEventListener('keydown',(e) => {
            if (e.key ==='Escape' && !this.modalOverlayEl.hidden) {
                this._closeModal();
            }
        });
    }

    _openModalFromGoal(btn) {
        const label = btn.dataset.goalLabel || '';
        const description = btn.dataset.goalDescription || '';
        const iconName = btn.dataset.goalIcon || '';
        const amount = parseFloat(btn.dataset.goalAmount || '0');

        const reached = this.currentTotal >= amount;
        const remaining = Math.max(0, amount - this.currentTotal);

        if (this.modalTitleEl) this.modalTitleEl.textContent = label;
        if (this.modalBodyEl) this.modalBodyEl.textContent = description;

        if (this.modalStatusEl) {
            if (reached) {
                this.modalStatusEl.textContent = `Unlocked 🎉`;
            } else {
                // You won't reach this branch because click is blocked,
                // but it keeps logic correct if you change rules later.
                this.modalStatusEl.textContent = `Spend ${this._formatMoney(remaining)} more to unlock`;
            }
        }

        // Icon (Twig icon bank clone)
        if (this.modalIconEl) {
            this.modalIconEl.innerHTML = '';
            const template = this.el.querySelector(`[data-mb-icon-template="${CSS.escape(iconName)}"]`);
            if (template) this.modalIconEl.innerHTML = template.innerHTML;
        }

        this.modalOverlayEl.hidden = false;
        this.modalEl.setAttribute('aria-hidden', 'false');
    }

    _closeModal() {
        this.modalEl.setAttribute('aria-hidden','true');
        this.modalOverlayEl.hidden =true;
    }



}
