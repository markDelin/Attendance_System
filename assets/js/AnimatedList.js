/**
 * assets/js/AnimatedList.js
 * Vanilla implementation of the AnimatedList component features.
 */

class AnimatedList {
  constructor(container, options = {}) {
    this.container = container;
    this.list = container.querySelector('.scroll-list') || container;
    this.items = Array.from(this.list.querySelectorAll('.animated-item'));
    this.options = {
      showGradients: true,
      enableArrowNavigation: true,
      initialSelectedIndex: -1,
      onItemSelect: null,
      ...options
    };

    this.selectedIndex = this.options.initialSelectedIndex;
    this.keyboardNav = false;
    this.topGradient = container.querySelector('.top-gradient');
    this.bottomGradient = container.querySelector('.bottom-gradient');

    this.init();
  }

  init() {
    this.setupIntersectionObserver();
    this.setupScrollListener();
    this.setupKeyboardNavigation();
    this.setupMouseEvents();

    // Initial scroll check for gradients
    this.updateGradients();
  }

  setupIntersectionObserver() {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const index = parseInt(entry.target.dataset.index || 0);
            const delay = index * 0.05; // 50ms stagger
            entry.target.style.transitionDelay = `${delay}s`;
            entry.target.classList.add('in-view');
            observer.unobserve(entry.target); // Animate in once
          }
        });
      },
      { threshold: 0.1, root: null } // Use viewport for better mobile reliability
    );

    this.items.forEach((item, index) => {
      item.dataset.index = index;
      observer.observe(item);
    });
  }

  setupScrollListener() {
    this.list.addEventListener('scroll', () => {
      this.updateGradients();
    }, { passive: true });
  }

  updateGradients() {
    if (!this.options.showGradients) return;

    const { scrollTop, scrollHeight, clientHeight } = this.list;
    
    if (this.topGradient) {
      this.topGradient.style.opacity = Math.min(scrollTop / 50, 1);
    }
    
    if (this.bottomGradient) {
      const bottomDistance = scrollHeight - (scrollTop + clientHeight);
      this.bottomGradient.style.opacity = scrollHeight <= clientHeight ? 0 : Math.min(bottomDistance / 50, 1);
    }
  }

  setupKeyboardNavigation() {
    if (!this.options.enableArrowNavigation) return;

    window.addEventListener('keydown', (e) => {
      // Don't navigate if an input or modal is focused (but SweetAlert might be open)
      if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName) || document.querySelector('.swal2-shown')) {
        return;
      }

      if (e.key === 'ArrowDown' || (e.key === 'Tab' && !e.shiftKey)) {
        e.preventDefault();
        this.keyboardNav = true;
        this.setSelectedIndex(Math.min(this.selectedIndex + 1, this.items.length - 1));
      } else if (e.key === 'ArrowUp' || (e.key === 'Tab' && e.shiftKey)) {
        e.preventDefault();
        this.keyboardNav = true;
        this.setSelectedIndex(Math.max(this.selectedIndex - 1, 0));
      } else if (e.key === 'Enter') {
        if (this.selectedIndex >= 0 && this.selectedIndex < this.items.length) {
          e.preventDefault();
          this.handleItemAction(this.items[this.selectedIndex], this.selectedIndex);
        }
      }
    });

    // Check for focus on the container to enable navigation
    this.container.addEventListener('focus', () => {
        if (this.selectedIndex === -1 && this.items.length > 0) {
            this.setSelectedIndex(0);
        }
    });
  }

  setupMouseEvents() {
    this.items.forEach((item, index) => {
      item.addEventListener('mouseenter', () => {
        if (!this.keyboardNav) {
          this.setSelectedIndex(index);
        }
      });

      item.addEventListener('click', (e) => {
        // If a specific interactive element was clicked (Edit, Delete, etc), do not trigger the default item action
        if (e.target.closest('a, button, input, select, textarea')) {
          this.setSelectedIndex(index);
          return;
        }

        this.setSelectedIndex(index);
        this.handleItemAction(item, index);
      });
    });

    this.list.addEventListener('mousemove', () => {
      this.keyboardNav = false;
    });
  }

  setSelectedIndex(index) {
    if (this.selectedIndex >= 0 && this.selectedIndex < this.items.length) {
      const prevItem = this.items[this.selectedIndex].querySelector('.item');
      if (prevItem) prevItem.classList.remove('selected');
    }

    this.selectedIndex = index;

    if (this.selectedIndex >= 0 && this.selectedIndex < this.items.length) {
      const currentItem = this.items[this.selectedIndex].querySelector('.item');
      if (currentItem) currentItem.classList.add('selected');

      if (this.keyboardNav) {
        this.scrollToItem(this.items[this.selectedIndex]);
      }
    }
  }

  scrollToItem(item) {
    const container = this.list;
    const extraMargin = 50;
    const containerScrollTop = container.scrollTop;
    const containerHeight = container.clientHeight;
    const itemTop = item.offsetTop;
    const itemBottom = itemTop + item.offsetHeight;

    if (itemTop < containerScrollTop + extraMargin) {
      container.scrollTo({ top: itemTop - extraMargin, behavior: 'smooth' });
    } else if (itemBottom > containerScrollTop + containerHeight - extraMargin) {
      container.scrollTo({
        top: itemBottom - containerHeight + extraMargin,
        behavior: 'smooth'
      });
    }
  }

  handleItemAction(item, index) {
    if (this.options.onItemSelect) {
      this.options.onItemSelect(item, index);
    } else {
      // Default action: Click the first link or primary button if found
      const primaryLink = item.querySelector('a, button:not(.btn-status)');
      if (primaryLink) {
        primaryLink.click();
      }
    }
  }
}

/**
 * Helper to initialize the animated list on a parent container.
 */
function initAnimatedList(selector, options = {}) {
  const containers = document.querySelectorAll(selector);
  containers.forEach(container => {
    new AnimatedList(container, options);
  });
}
