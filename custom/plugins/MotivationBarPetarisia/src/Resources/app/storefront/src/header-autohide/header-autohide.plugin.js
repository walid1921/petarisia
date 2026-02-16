import Plugin from 'src/plugin-system/plugin.class';

export default class HeaderAutohidePlugin extends Plugin {
    init() {
        this.lastScrollTop = 0;
        this.scrollThreshold = 5;
        this.headerHeight = this.el.offsetHeight;

        this._registerEvents();
    }

    _registerEvents() {
        window.addEventListener('scroll', this._onScroll.bind(this), { passive: true });
    }

    _onScroll() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

        if (scrollTop < 0) return;

        if (scrollTop < this.headerHeight) {
            this.el.classList.remove('header-hidden');
            this.el.classList.add('header-visible');
            return;
        }

        if (Math.abs(scrollTop - this.lastScrollTop) < this.scrollThreshold) {
            return;
        }

        if (scrollTop > this.lastScrollTop) {
            this.el.classList.add('header-hidden');
            this.el.classList.remove('header-visible');
        } else {
            this.el.classList.remove('header-hidden');
            this.el.classList.add('header-visible');
        }

        this.lastScrollTop = scrollTop;
    }
}
