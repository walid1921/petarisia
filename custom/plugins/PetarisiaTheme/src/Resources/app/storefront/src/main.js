console.log('Worked');

//! Drag and scroll Slider for mouse

document.addEventListener('DOMContentLoaded', function () {
    const sliders = document.querySelectorAll('.product-slider-scroll-container');

    sliders.forEach(slider => {
        let isDown = false;
        let startX;
        let scrollLeft;

        slider.addEventListener('mousedown', (e) => {
            isDown = true;
            slider.classList.add('active');
            startX = e.pageX - slider.offsetLeft;
            scrollLeft = slider.scrollLeft;
        });

        slider.addEventListener('mouseleave', () => {
            isDown = false;
        });

        slider.addEventListener('mouseup', () => {
            isDown = false;
        });

        slider.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - slider.offsetLeft;
            const walk = (x - startX) * 2; // Scroll speed multiplier
            slider.scrollLeft = scrollLeft - walk;
        });

        // Prevent click events on links when dragging
        slider.addEventListener('click', (e) => {
            if (Math.abs(slider.scrollLeft - scrollLeft) > 5) {
                e.preventDefault();
            }
        }, true);
    });
});
