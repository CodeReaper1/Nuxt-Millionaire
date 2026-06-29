<template>
  <main class="test-page">
    <div class="container">

      <!-- Page header -->
      <div class="page-header">
        <h1 class="page-title">Card Design</h1>
        <p class="page-desc">A collection of UI cards used across the store.</p>
      </div>

      <!-- ══════════════ FEATURE CARDS ══════════════ -->
      <section class="card-section">
        <h2 class="section-label">Features</h2>
        <div class="feature-grid">
          <div class="feature-card">
            <div class="card-stack-bg" />
            <div class="card-stack-mid" />
            <div class="card-inner">
              <div class="card-icon-wrap" style="--icon-color: #2d6a4f;">
                <Icon name="ion:rocket-outline" size="22" />
              </div>
              <h3 class="card-title">Бърза доставка</h3>
              <p class="card-body">До 1–2 работни дни в цяла България чрез Еконт.</p>
              <span class="card-tag">Безплатно над 50 €</span>
            </div>
          </div>

          <div class="feature-card">
            <div class="card-stack-bg" />
            <div class="card-stack-mid" />
            <div class="card-inner">
              <div class="card-icon-wrap" style="--icon-color: #1565c0;">
                <Icon name="ion:shield-checkmark-outline" size="22" />
              </div>
              <h3 class="card-title">Сигурно плащане</h3>
              <p class="card-body">Карта, PayPal или наложен платеж — вие избирате.</p>
              <span class="card-tag">SSL защита</span>
            </div>
          </div>

          <div class="feature-card">
            <div class="card-stack-bg" />
            <div class="card-stack-mid" />
            <div class="card-inner">
              <div class="card-icon-wrap" style="--icon-color: #6a1e9a;">
                <Icon name="ion:refresh-outline" size="22" />
              </div>
              <h3 class="card-title">14 дни връщане</h3>
              <p class="card-body">Без въпроси — върни продукта и получи парите си.</p>
              <span class="card-tag">Без такса</span>
            </div>
          </div>

          <div class="feature-card">
            <div class="card-stack-bg" />
            <div class="card-stack-mid" />
            <div class="card-inner">
              <div class="card-icon-wrap" style="--icon-color: #b85c00;">
                <Icon name="ion:headset-outline" size="22" />
              </div>
              <h3 class="card-title">Поддръжка 24/7</h3>
              <p class="card-body">Нашият екип е на линия по всяко време на деня.</p>
              <span class="card-tag">Бърз отговор</span>
            </div>
          </div>
        </div>
      </section>

      <!-- ══════════════ STEP CARDS ══════════════ -->
      <section class="card-section">
        <h2 class="section-label">How it works</h2>
        <div class="step-grid">
          <div v-for="(step, i) in steps" :key="i" class="step-card">
            <div class="step-number">{{ String(i + 1).padStart(2, '0') }}</div>
            <div class="step-icon-ring">
              <Icon :name="step.icon" size="20" />
            </div>
            <div class="step-content">
              <h3 class="step-title">{{ step.title }}</h3>
              <p class="step-desc">{{ step.desc }}</p>
            </div>
          </div>
        </div>
      </section>

      <!-- ══════════════ STAT CARDS ══════════════ -->
      <section class="card-section">
        <h2 class="section-label">Stats</h2>
        <div class="stat-grid">
          <div v-for="stat in stats" :key="stat.label" class="stat-card">
            <div class="stat-card-glow" :style="{ background: stat.glow }" />
            <span class="stat-value">{{ stat.value }}</span>
            <span class="stat-label">{{ stat.label }}</span>
            <span class="stat-sub">{{ stat.sub }}</span>
          </div>
        </div>
      </section>

      <!-- ══════════════ LOYALTY CARD ══════════════ -->
      <section class="card-section">
        <h2 class="section-label">Loyalty</h2>
        <div class="loyalty-wrap">
          <div class="loyalty-card">
            <div class="loyalty-bg-circle lc-1" />
            <div class="loyalty-bg-circle lc-2" />
            <div class="loyalty-header">
              <span class="loyalty-label">Програма за лоялност</span>
              <span class="loyalty-badge">
                <Icon name="ion:star" size="11" />
                Gold
              </span>
            </div>
            <div class="loyalty-points">2 450</div>
            <div class="loyalty-sub">налични точки · стойност ~24,50 €</div>
            <div class="loyalty-progress-wrap">
              <div class="loyalty-progress-track">
                <div class="loyalty-progress-bar" style="width: 68%" />
              </div>
              <div class="loyalty-progress-meta">
                <span>Silver</span>
                <span>550 точки до Gold Max</span>
              </div>
            </div>
          </div>
        </div>
      </section>

    </div>
  </main>
</template>

<script setup lang="ts">
useSeoMeta({ title: 'Card Design Test' });

const steps = [
  { icon: 'ion:search-outline', title: 'Разгледай', desc: 'Намери продукта по категория, цвят или търсене.' },
  { icon: 'ion:cart-outline', title: 'Добави', desc: 'Избери размер и количество и добави към кошницата.' },
  { icon: 'ion:card-outline', title: 'Плати', desc: 'Завърши поръчката сигурно с предпочитан метод.' },
  { icon: 'ion:cube-outline', title: 'Получи', desc: 'Пратката пристига до 2 дни на адрес или Еконт.' },
];

const stats = [
  { value: '500+', label: 'Продукта', sub: 'в магазина', glow: 'radial-gradient(circle at 50% 0%, rgba(45,106,79,.35), transparent 70%)' },
  { value: '4.9★', label: 'Рейтинг', sub: 'среден от клиенти', glow: 'radial-gradient(circle at 50% 0%, rgba(245,196,48,.35), transparent 70%)' },
  { value: '24/7', label: 'Поддръжка', sub: 'винаги на линия', glow: 'radial-gradient(circle at 50% 0%, rgba(21,101,192,.35), transparent 70%)' },
  { value: 'Free', label: 'Доставка', sub: 'при поръчка над 50 €', glow: 'radial-gradient(circle at 50% 0%, rgba(106,30,154,.35), transparent 70%)' },
];
</script>

<style scoped>
/* ── Base ── */
.test-page {
  --green: #2d6a4f;
  --green-light: #40916c;
  --cream: #f5f3ed;
  --radius: 16px;
  --card-shadow: 0 2px 8px rgba(0,0,0,.06), 0 8px 24px rgba(0,0,0,.06);
  padding: 3rem 0 6rem;
}

.page-header {
  margin-bottom: 3rem;
}

.page-title {
  font-size: 2rem;
  font-weight: 800;
  color: #1a1a2e;
  letter-spacing: -.03em;
}

.dark .page-title { color: #fff; }

.page-desc {
  color: #777;
  margin-top: .25rem;
  font-size: .95rem;
}

.dark .page-desc { color: #aaa; }

/* ── Section ── */
.card-section {
  margin-bottom: 4rem;
}

.section-label {
  font-size: .7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .12em;
  color: #aaa;
  margin-bottom: 1.25rem;
}

.dark .section-label { color: #666; }

/* ══════════════ FEATURE CARDS ══════════════ */
.feature-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.5rem;
}

@media (min-width: 768px) {
  .feature-grid { grid-template-columns: repeat(4, 1fr); }
}

.feature-card {
  position: relative;
  isolation: isolate;
}

/* Stack layers behind the card */
.card-stack-bg,
.card-stack-mid {
  position: absolute;
  inset: 0;
  border-radius: var(--radius);
  background: #fff;
  border: 1px solid rgba(0,0,0,.06);
  pointer-events: none;
}

.dark .card-stack-bg,
.dark .card-stack-mid {
  background: #1e1e32;
  border-color: rgba(255,255,255,.05);
}

.card-stack-bg {
  transform: translateY(8px) scale(.92);
  opacity: .45;
  z-index: -2;
}

.card-stack-mid {
  transform: translateY(4px) scale(.96);
  opacity: .7;
  z-index: -1;
}

.card-inner {
  background: #fff;
  border: 1px solid rgba(0,0,0,.06);
  border-radius: var(--radius);
  padding: 1.5rem 1.25rem 1.25rem;
  box-shadow: var(--card-shadow);
  position: relative;
  z-index: 0;
  transition: transform .2s, box-shadow .2s;
}

.feature-card:hover .card-inner {
  transform: translateY(-3px);
  box-shadow: 0 4px 16px rgba(0,0,0,.08), 0 16px 40px rgba(0,0,0,.08);
}

.feature-card:hover .card-stack-mid { transform: translateY(6px) scale(.96); }
.feature-card:hover .card-stack-bg  { transform: translateY(10px) scale(.92); }

.dark .card-inner {
  background: #1e1e32;
  border-color: rgba(255,255,255,.07);
}

.card-icon-wrap {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: color-mix(in srgb, var(--icon-color) 12%, transparent);
  color: var(--icon-color);
  margin-bottom: 1rem;
}

.card-title {
  font-size: 1rem;
  font-weight: 700;
  color: #1a1a2e;
  margin-bottom: .35rem;
  line-height: 1.2;
}

.dark .card-title { color: #f0f0f0; }

.card-body {
  font-size: .83rem;
  color: #666;
  line-height: 1.5;
  margin-bottom: 1rem;
}

.dark .card-body { color: #999; }

.card-tag {
  display: inline-block;
  font-size: .72rem;
  font-weight: 600;
  color: var(--green);
  background: color-mix(in srgb, var(--green) 10%, transparent);
  padding: .2rem .6rem;
  border-radius: 50px;
}

.dark .card-tag {
  color: #5cb88a;
  background: rgba(45,106,79,.15);
}

/* ══════════════ STEP CARDS ══════════════ */
.step-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1rem;
}

@media (min-width: 640px) { .step-grid { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 1024px) { .step-grid { grid-template-columns: repeat(4, 1fr); } }

.step-card {
  display: flex;
  align-items: flex-start;
  gap: .875rem;
  background: #fff;
  border: 1px solid rgba(0,0,0,.06);
  border-radius: var(--radius);
  padding: 1.25rem;
  box-shadow: var(--card-shadow);
  transition: transform .2s;
}

.step-card:hover { transform: translateY(-3px); }

.dark .step-card {
  background: #1e1e32;
  border-color: rgba(255,255,255,.07);
}

.step-number {
  font-size: .7rem;
  font-weight: 800;
  color: #ccc;
  letter-spacing: .05em;
  flex-shrink: 0;
  margin-top: .1rem;
}

.dark .step-number { color: #444; }

.step-icon-ring {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--cream);
  color: var(--green);
  flex-shrink: 0;
}

.dark .step-icon-ring {
  background: rgba(45,106,79,.15);
  color: #5cb88a;
}

.step-content { flex: 1; min-width: 0; }

.step-title {
  font-size: .92rem;
  font-weight: 700;
  color: #1a1a2e;
  margin-bottom: .2rem;
}

.dark .step-title { color: #f0f0f0; }

.step-desc {
  font-size: .8rem;
  color: #777;
  line-height: 1.4;
}

.dark .step-desc { color: #999; }

/* ══════════════ STAT CARDS ══════════════ */
.stat-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}

@media (min-width: 768px) { .stat-grid { grid-template-columns: repeat(4, 1fr); } }

.stat-card {
  position: relative;
  overflow: hidden;
  background: #fff;
  border: 1px solid rgba(0,0,0,.06);
  border-radius: var(--radius);
  padding: 1.5rem 1.25rem 1.25rem;
  box-shadow: var(--card-shadow);
  text-align: center;
  transition: transform .2s;
}

.stat-card:hover { transform: translateY(-3px); }

.dark .stat-card {
  background: #1e1e32;
  border-color: rgba(255,255,255,.07);
}

.stat-card-glow {
  position: absolute;
  inset: 0;
  pointer-events: none;
  opacity: 1;
}

.stat-value {
  display: block;
  font-size: 1.75rem;
  font-weight: 800;
  color: #1a1a2e;
  letter-spacing: -.03em;
  line-height: 1;
  position: relative;
}

.dark .stat-value { color: #fff; }

.stat-label {
  display: block;
  font-size: .8rem;
  font-weight: 600;
  color: #555;
  margin-top: .35rem;
  position: relative;
}

.dark .stat-label { color: #ccc; }

.stat-sub {
  display: block;
  font-size: .72rem;
  color: #aaa;
  margin-top: .15rem;
  position: relative;
}

.dark .stat-sub { color: #666; }

/* ══════════════ LOYALTY CARD ══════════════ */
.loyalty-wrap {
  max-width: 440px;
}

.loyalty-card {
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #1b4332, #2d6a4f);
  border-radius: 20px;
  padding: 2rem;
  color: #fff;
  box-shadow: 0 8px 32px rgba(45,106,79,.35);
}

.loyalty-bg-circle {
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
}

.lc-1 {
  width: 200px;
  height: 200px;
  background: rgba(255,255,255,.06);
  top: -60px;
  right: -60px;
}

.lc-2 {
  width: 120px;
  height: 120px;
  background: rgba(255,255,255,.04);
  bottom: -30px;
  left: 20px;
}

.loyalty-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
}

.loyalty-label {
  font-size: .78rem;
  font-weight: 600;
  opacity: .7;
  text-transform: uppercase;
  letter-spacing: .08em;
}

.loyalty-badge {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  font-size: .72rem;
  font-weight: 700;
  background: rgba(244,196,48,.2);
  color: #f4c430;
  border: 1px solid rgba(244,196,48,.3);
  padding: .25rem .6rem;
  border-radius: 50px;
  text-transform: uppercase;
  letter-spacing: .05em;
}

.loyalty-points {
  font-size: 3rem;
  font-weight: 800;
  letter-spacing: -.04em;
  line-height: 1;
  margin-bottom: .4rem;
}

.loyalty-sub {
  font-size: .82rem;
  opacity: .65;
  margin-bottom: 1.75rem;
}

.loyalty-progress-wrap { }

.loyalty-progress-track {
  height: 6px;
  background: rgba(255,255,255,.15);
  border-radius: 50px;
  overflow: hidden;
  margin-bottom: .6rem;
}

.loyalty-progress-bar {
  height: 100%;
  background: linear-gradient(to right, #f4c430, #fde080);
  border-radius: 50px;
  transition: width .7s ease;
}

.loyalty-progress-meta {
  display: flex;
  justify-content: space-between;
  font-size: .75rem;
  opacity: .65;
}
</style>
