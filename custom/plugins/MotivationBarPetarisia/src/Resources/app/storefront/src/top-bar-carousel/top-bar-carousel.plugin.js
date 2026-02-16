import Plugin from 'src/plugin-system/plugin.class';

export default class TopBarCarouselPlugin extends Plugin {
    init() {
        this.items = this.el.querySelectorAll('.top-bar-carousel-item');
        this.currentIndex = 0;
        this.intervalTime = 3000;

        if (this.items.length > 0) {
            this._startCarousel();
        }
    }

    _startCarousel() {
        setInterval(() => {
            this._nextSlide();
        }, this.intervalTime);
    }

    _nextSlide() {
        this.items.forEach(item => {
            item.classList.remove('active', 'fade-out');
        });

        this.items[this.currentIndex].classList.add('fade-out');

        this.currentIndex = (this.currentIndex + 1) % this.items.length;
        this.items[this.currentIndex].classList.add('active');
    }
}
