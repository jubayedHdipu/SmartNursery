/**
 * Smart Nursery Management System - Botanical Showcase & 3D Interactive JS
 * Component 1: Google Nest-Inspired 10-Frame Living Botanical 3D Accordion
 * Component 2: Verified In-Stock Architectural Specimen Marketplace Cards
 */

document.addEventListener('DOMContentLoaded', () => {
    // =========================================================================
    // 1. GOOGLE NEST-INSPIRED 10-FRAME 3D ACCORDION
    // =========================================================================
    const nestFrames = document.querySelectorAll('.nest-frame');
    const nestTrack = document.querySelector('.nest-accordion-track');

    if (nestFrames.length > 0) {
        // Set first frame as default active on load if none selected
        if (!document.querySelector('.nest-frame.is-active')) {
            nestFrames[0].classList.add('is-active');
        }

        nestFrames.forEach((frame, idx) => {
            // Hover / Enter
            frame.addEventListener('mouseenter', () => {
                nestFrames.forEach(f => f.classList.remove('is-active'));
                frame.classList.add('is-active');
            });

            // Click / Tap (Mobile & Touch support)
            frame.addEventListener('click', (e) => {
                // If clicking an inner interactive button, allow default
                if (e.target.closest('button') || e.target.closest('a')) {
                    return;
                }
                nestFrames.forEach(f => f.classList.remove('is-active'));
                frame.classList.add('is-active');
            });

            // 3D Parallax & Glare on Mouse Move
            frame.addEventListener('mousemove', (e) => {
                const rect = frame.getBoundingClientRect();
                const x = ((e.clientX - rect.left) / rect.width) * 2 - 1; // -1 to 1
                const y = ((e.clientY - rect.top) / rect.height) * 2 - 1;  // -1 to 1

                frame.style.setProperty('--mouse-x', x.toFixed(3));
                frame.style.setProperty('--mouse-y', y.toFixed(3));
            });

            // Reset on Mouse Leave
            frame.addEventListener('mouseleave', () => {
                frame.style.setProperty('--mouse-x', '0');
                frame.style.setProperty('--mouse-y', '0');
            });
        });
    }

    // =========================================================================
    // 2. SPECIMEN QUICK-INSPECTION MODAL CONTROLLER
    // =========================================================================
    const modalBackdrop = document.getElementById('specimen-inspect-modal');
    const inspectButtons = document.querySelectorAll('.btn-inspect-specimen');
    const modalCloseButtons = document.querySelectorAll('.btn-close-modal');

    const openSpecimenModal = (data) => {
        if (!modalBackdrop) return;

        // Populate Modal Fields
        const modalImg = document.getElementById('modal-specimen-img');
        const modalTitle = document.getElementById('modal-specimen-title');
        const modalScientific = document.getElementById('modal-specimen-scientific');
        const modalNursery = document.getElementById('modal-specimen-nursery');
        const modalPrice = document.getElementById('modal-specimen-price');
        const modalDesc = document.getElementById('modal-specimen-desc');
        const modalCare = document.getElementById('modal-specimen-care');
        const modalLux = document.getElementById('modal-specimen-lux');
        const modalWater = document.getElementById('modal-specimen-water');
        const modalCartBtn = document.getElementById('modal-specimen-cart-btn');

        if (modalImg) modalImg.src = data.img || '';
        if (modalTitle) modalTitle.textContent = data.title || 'Botanical Specimen';
        if (modalScientific) modalScientific.textContent = data.scientific || '';
        if (modalNursery) modalNursery.textContent = data.nursery || '';
        if (modalPrice) modalPrice.textContent = data.price || '';
        if (modalDesc) modalDesc.textContent = data.desc || '';
        if (modalCare) modalCare.textContent = data.care || 'Easy Maintenance';
        if (modalLux) modalLux.textContent = data.lux || '3,000 - 5,000 lx';
        if (modalWater) modalWater.textContent = data.water || 'Once / Week';

        if (modalCartBtn) {
            modalCartBtn.onclick = () => {
                if (data.id && typeof window.quickAddToCart === 'function') {
                    window.quickAddToCart(data.id);
                } else if (typeof window.showToast === 'function') {
                    window.showToast(`🌿 Added ${data.title} to your collection!`, 'success');
                }
                closeSpecimenModal();
            };
        }

        modalBackdrop.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    };

    const closeSpecimenModal = () => {
        if (!modalBackdrop) return;
        modalBackdrop.classList.remove('is-open');
        document.body.style.overflow = '';
    };

    inspectButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();

            const data = {
                id: btn.getAttribute('data-id') || 1,
                title: btn.getAttribute('data-title') || '',
                scientific: btn.getAttribute('data-scientific') || '',
                nursery: btn.getAttribute('data-nursery') || '',
                price: btn.getAttribute('data-price') || '',
                desc: btn.getAttribute('data-desc') || '',
                img: btn.getAttribute('data-img') || '',
                care: btn.getAttribute('data-care') || '',
                lux: btn.getAttribute('data-lux') || '',
                water: btn.getAttribute('data-water') || ''
            };

            openSpecimenModal(data);
        });
    });

    if (modalCloseButtons) {
        modalCloseButtons.forEach(btn => {
            btn.addEventListener('click', closeSpecimenModal);
        });
    }

    if (modalBackdrop) {
        modalBackdrop.addEventListener('click', (e) => {
            if (e.target === modalBackdrop) {
                closeSpecimenModal();
            }
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modalBackdrop && modalBackdrop.classList.contains('is-open')) {
            closeSpecimenModal();
        }
    });

    // =========================================================================
    // 3. SPECIMEN CARD MULTI-IMAGE THUMBNAIL SWITCHER
    // =========================================================================
    const specimenCards = document.querySelectorAll('.specimen-card');

    specimenCards.forEach(card => {
        const mainImg = card.querySelector('.specimen-main-img');
        const thumbDots = card.querySelectorAll('.specimen-thumb-dot');

        if (mainImg && thumbDots.length > 0) {
            thumbDots.forEach(dot => {
                const switchImage = () => {
                    const newSrc = dot.getAttribute('data-src');
                    if (!newSrc || mainImg.src.includes(newSrc)) return;

                    thumbDots.forEach(d => d.classList.remove('is-active'));
                    dot.classList.add('is-active');

                    mainImg.style.opacity = '0.3';
                    mainImg.style.transform = 'scale(0.98)';

                    setTimeout(() => {
                        mainImg.src = newSrc;
                        mainImg.style.opacity = '1';
                        mainImg.style.transform = 'scale(1)';
                    }, 150);
                };

                dot.addEventListener('mouseenter', switchImage);
                dot.addEventListener('click', switchImage);
            });
        }
    });
});
