// Мобильное меню
document.addEventListener('DOMContentLoaded', function() {
    const hamburger = document.getElementById('hamburger');
    const navMenu = document.getElementById('nav-menu');
    
    if (hamburger && navMenu) {
        hamburger.addEventListener('click', function() {
            navMenu.classList.toggle('active');
            hamburger.classList.toggle('active');
        });
    }

    // Плавная прокрутка для якорных ссылок
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    anchorLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                const offsetTop = targetElement.offsetTop - 70;
                window.scrollTo({
                    top: offsetTop,
                    behavior: 'smooth'
                });
            }
        });
    });

    // Анимация появления элементов при скролле
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Наблюдаем за элементами
    const animatedElements = document.querySelectorAll('.course-card, .review-card, .advantage, .stat');
    animatedElements.forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });

    // Валидация формы
    const bookingForm = document.querySelector('form[action="booking.php"]');
    if (bookingForm) {
        bookingForm.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#e74c3c';
                    field.addEventListener('input', function() {
                        this.style.borderColor = '#e1e8ed';
                    });
                } else {
                    field.style.borderColor = '#27ae60';
                }
            });
            
            // Валидация email
            const emailField = this.querySelector('[type="email"]');
            if (emailField && emailField.value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(emailField.value)) {
                    isValid = false;
                    emailField.style.borderColor = '#e74c3c';
                    showNotification('Пожалуйста, введите корректный email адрес', 'error');
                }
            }
            
            // Валидация телефона
            const phoneField = this.querySelector('[type="tel"]');
            if (phoneField && phoneField.value) {
                const phoneRegex = /^\+?[\d\s\-\(\)]+$/;
                if (!phoneRegex.test(phoneField.value) || phoneField.value.length < 8) {
                    isValid = false;
                    phoneField.style.borderColor = '#e74c3c';
                    showNotification('Пожалуйста, введите корректный номер телефона', 'error');
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                showNotification('Пожалуйста, заполните все обязательные поля корректно', 'error');
            }
        });
    }

    // Форма обратной связи
    const contactForm = document.querySelector('form[action="process_contact.php"]');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const button = this.querySelector('button[type="submit"]');
            const originalText = button.innerHTML;
            
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Отправка...';
            button.disabled = true;
            
            fetch('process_contact.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Сообщение успешно отправлено!', 'success');
                    this.reset();
                } else {
                    showNotification('Произошла ошибка при отправке сообщения', 'error');
                }
            })
            .catch(error => {
                showNotification('Произошла ошибка при отправке сообщения', 'error');
            })
            .finally(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            });
        });
    }

    // Автозаполнение курса из URL
    const urlParams = new URLSearchParams(window.location.search);
    const courseId = urlParams.get('course_id');
    if (courseId) {
        const courseSelect = document.getElementById('course_id');
        if (courseSelect) {
            courseSelect.value = courseId;
        }
    }

    // Ограничение даты в форме (только будущие даты)
    const dateInput = document.getElementById('preferred_date');
    if (dateInput) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.setAttribute('min', today);
    }

    // Подсчет символов в textarea
    const messageTextarea = document.getElementById('message');
    if (messageTextarea) {
        const maxLength = 500;
        const counter = document.createElement('div');
        counter.style.textAlign = 'right';
        counter.style.fontSize = '0.9rem';
        counter.style.color = '#7f8c8d';
        counter.style.marginTop = '0.5rem';
        messageTextarea.parentNode.appendChild(counter);
        
        function updateCounter() {
            const remaining = maxLength - messageTextarea.value.length;
            counter.textContent = `${remaining} символов осталось`;
            if (remaining < 50) {
                counter.style.color = '#e74c3c';
            } else {
                counter.style.color = '#7f8c8d';
            }
        }
        
        messageTextarea.setAttribute('maxlength', maxLength);
        messageTextarea.addEventListener('input', updateCounter);
        updateCounter();
    }
});

// Функция для показа уведомлений
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        ${message}
        <button class="notification-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    
    // Стили для уведомления
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#d4edda' : type === 'error' ? '#f8d7da' : '#d1ecf1'};
        color: ${type === 'success' ? '#155724' : type === 'error' ? '#721c24' : '#0c5460'};
        padding: 1rem 1.5rem;
        border-radius: 5px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        z-index: 9999;
        max-width: 400px;
        animation: slideIn 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    `;
    
    const closeButton = notification.querySelector('.notification-close');
    closeButton.style.cssText = `
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        margin-left: auto;
        padding: 0;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    
    document.body.appendChild(notification);
    
    // Автоматически удаляем уведомление через 5 секунд
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }
    }, 5000);
}

// CSS анимации для уведомлений
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Скрытие/показ навигации при скролле
let lastScrollTop = 0;
window.addEventListener('scroll', function() {
    const navbar = document.querySelector('.navbar');
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    
    if (scrollTop > lastScrollTop && scrollTop > 100) {
        // Скроллим вниз
        navbar.style.transform = 'translateY(-100%)';
    } else {
        // Скроллим вверх
        navbar.style.transform = 'translateY(0)';
    }
    
    lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
});

// Показ индикатора загрузки для форм
function showLoadingIndicator(form) {
    const indicator = document.createElement('div');
    indicator.className = 'loading-indicator';
    indicator.innerHTML = '<div class="spinner"></div><p>Обрабатываем ваш запрос...</p>';
    indicator.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        z-index: 10000;
        color: white;
    `;
    
    const spinner = indicator.querySelector('.spinner');
    spinner.style.cssText = `
        width: 50px;
        height: 50px;
        border: 4px solid rgba(255,255,255,0.3);
        border-top: 4px solid white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 1rem;
    `;
    
    document.body.appendChild(indicator);
    return indicator;
}

// Добавляем CSS для спиннера
const spinnerStyle = document.createElement('style');
spinnerStyle.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(spinnerStyle);

