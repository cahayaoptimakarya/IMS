<style>
    .card-animate {
        animation: fadeIn-slideUp 0.5s ease-out forwards;
        opacity: 0;
    }

    @keyframes fadeIn-slideUp {
        0% {
            opacity: 0;
            transform: translateY(20px);
        }
        100% {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .summary-card {
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }

    .summary-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 20px rgba(0,0,0,0.15);
    }

    /* Status card styling */
    .status-card-icon .symbol-label {
        background: #F3E8FF; /* light purple */
    }
    .status-card-icon i {
        color: #7C3AED; /* purple */
    }
    .status-metrics {
        display: flex;
        gap: 1.5rem;
        align-items: center;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .status-metrics .metric {
        text-align: center;
        min-width: 88px;
    }
    .status-metrics .chip {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 1rem;
        line-height: 1;
    }
    .chip-warning { background: #FFF7E6; color: #F59E0B; }
    .chip-primary { background: #E6F0FF; color: #3B82F6; }
    .chip-success { background: #ECFDF5; color: #10B981; }

    @media (max-width: 576px) {
        .status-metrics { justify-content: flex-start; }
        .status-metrics .metric { min-width: 70px; }
    }
</style>
