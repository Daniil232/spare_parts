<!-- Футер (подвал) -->
<footer class="site-footer">
    <div class="footer-content">
        <div class="footer-section">
            <h4>📞 Контакты</h4>
            <p><strong>Директор:</strong> Саранов Олег Анатольевич</p>
            <p><strong>Телефон:</strong> +7 (921) 466-56-74</p>
            <p><strong>Email:</strong> info@spareparts.ru</p>
        </div>
        <div class="footer-section">
            <h4>📍 Адрес</h4>
            <p>г. Петрозаводск</p>
            <p>Черемуховый проезд, д. 1</p>
        </div>
        <div class="footer-section">
            <h4>🕐 Часы работы</h4>
            <p>Понедельник - Пятница: 9:00 - 18:00</p>
            <p>Суббота: 10:00 - 15:00</p>
            <p>Воскресенье: Выходной</p>
        </div>
        <div class="footer-section">
            <h4>🔧 О компании</h4>
            <p>Цифровые паспорта запчастей</p>
            <p>Лесозаготовительная техника</p>
        </div>
    </div>
</footer>

<style>
    .site-footer {
        background: #1a1a2e;
        color: #e0e0e0;
        padding: 30px 40px 20px;
        margin-top: 40px;
        border-top: 1px solid #2c3e50;
        width: 100%;
        box-sizing: border-box;
    }
    .footer-content {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 30px;
        max-width: 1200px;
        margin: 0 auto;
    }
    .footer-section {
        flex: 1;
        min-width: 200px;
    }
    .footer-section h4 {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 15px;
        color: white;
        position: relative;
        padding-bottom: 8px;
    }
    .footer-section h4::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 40px;
        height: 2px;
        background: #27ae60;
    }
    .footer-section p {
        font-size: 13px;
        line-height: 1.6;
        margin-bottom: 6px;
        color: #b0b0b0;
    }
    .footer-section p strong {
        color: white;
    }
    @media (max-width: 768px) {
        .site-footer {
            padding: 25px 20px 15px;
        }
        .footer-content {
            flex-direction: column;
            text-align: center;
            gap: 20px;
        }
        .footer-section h4::after {
            left: 50%;
            transform: translateX(-50%);
        }
    }
</style>