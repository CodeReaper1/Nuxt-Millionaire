<script lang="ts" setup>
import { ProductsOrderByEnum } from '#woo';

const { siteName, description, shortDescription, siteImage } = useAppConfig();

const { data } = await useAsyncGql('getProductCategories', { first: 6 });
const productCategories = data.value?.productCategories?.nodes || [];

const { data: productData } = await useAsyncGql('getProducts', { first: 8, orderby: ProductsOrderByEnum.POPULARITY });
const popularProducts = productData.value.products?.nodes || [];

useSeoMeta({
  title: 'Home',
  ogTitle: siteName,
  description: description,
  ogDescription: shortDescription,
  ogImage: siteImage,
  twitterCard: 'summary_large_image',
});
</script>

<template>
  <main class="landing-page">
    <!-- ═══════════════════════ HERO ═══════════════════════ -->
    <section class="hero-section">
      <div class="container">
        <div class="hero-grid">
          <div class="hero-content">
            <h1 class="hero-title">
              Открийте<br />вашия стил.
            </h1>
            <p class="hero-subtitle">
              Разгледайте нашата колекция от внимателно подбрани продукти.
              Качество, стил и достъпни цени на едно място.
            </p>
            <div class="hero-actions">
              <NuxtLink to="/products" class="btn-primary">
                Пазарувай сега
                <Icon name="ion:arrow-forward" size="18" />
              </NuxtLink>
              <NuxtLink to="/categories" class="btn-ghost">
                Разгледай категории
              </NuxtLink>
            </div>

            <div class="hero-stats">
              <div class="stat">
                <span class="stat-number">500+</span>
                <span class="stat-label">Продукти</span>
              </div>
              <div class="stat-divider" />
              <div class="stat">
                <span class="stat-number">24/7</span>
                <span class="stat-label">Поддръжка</span>
              </div>
              <div class="stat-divider" />
              <div class="stat">
                <span class="stat-number">Free</span>
                <span class="stat-label">Доставка 50€+</span>
              </div>
            </div>
          </div>

          <div class="hero-visual">
            <div class="hero-blob">
              <NuxtImg
                src="/images/hero-4.jpg"
                alt="Hero"
                width="600"
                height="600"
                class="hero-image"
                loading="eager"
                fetchpriority="high"
                preload />
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══════════════════ HOW IT WORKS ═══════════════════ -->
    <section class="how-section">
      <div class="container">
        <h2 class="section-title">Как работи?</h2>
        <p class="section-subtitle">Пазаруването при нас е лесно и бързо</p>

        <div class="steps-grid">
          <div class="step-card">
            <div class="step-icon">
              <Icon name="ion:search-outline" size="28" />
            </div>
            <h3>Разгледай</h3>
            <p>Открий продукти от нашата колекция по категория или търсене</p>
          </div>
          <div class="step-card">
            <div class="step-icon">
              <Icon name="ion:cart-outline" size="28" />
            </div>
            <h3>Добави в кошница</h3>
            <p>Избери размер, цвят и количество и добави към поръчката</p>
          </div>
          <div class="step-card">
            <div class="step-icon">
              <Icon name="ion:card-outline" size="28" />
            </div>
            <h3>Плати сигурно</h3>
            <p>Завърши поръчката с карта, наложен платеж или друг метод</p>
          </div>
          <div class="step-card">
            <div class="step-icon">
              <Icon name="ion:cube-outline" size="28" />
            </div>
            <h3>Получи доставка</h3>
            <p>Получи пратката на адрес или в офис на Еконт до 2 дни</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══════════════════ CATEGORIES ═══════════════════ -->
    <section class="categories-section" v-if="productCategories.length">
      <div class="container">
        <div class="section-header">
          <div>
            <h2 class="section-title">Категории</h2>
            <p class="section-subtitle">Разгледай по категория</p>
          </div>
          <NuxtLink to="/categories" class="view-all-link">
            Виж всички
            <Icon name="ion:arrow-forward" size="16" />
          </NuxtLink>
        </div>
        <div class="grid grid-cols-2 gap-4 mt-8 md:grid-cols-3 lg:grid-cols-6">
          <CategoryCard v-for="(category, i) in productCategories" :key="i" class="w-full" :node="category" />
        </div>
      </div>
    </section>

    <!-- ═══════════════════ POPULAR PRODUCTS ═══════════════════ -->
    <section class="products-section" v-if="popularProducts.length">
      <div class="container">
        <div class="section-header">
          <div>
            <h2 class="section-title">Популярни продукти</h2>
            <p class="section-subtitle">Най-търсените от нашите клиенти</p>
          </div>
          <NuxtLink to="/products" class="view-all-link">
            Виж всички
            <Icon name="ion:arrow-forward" size="16" />
          </NuxtLink>
        </div>
        <div class="products-grid mt-8">
          <ProductCard
            v-for="(node, i) in popularProducts"
            :key="node.databaseId"
            class="w-full"
            :node="node"
            :index="i" />
        </div>
      </div>
    </section>

    <!-- ═══════════════════ TESTIMONIAL ═══════════════════ -->
    <section class="testimonial-section">
      <div class="container">
        <div class="testimonial-card">
          <div class="testimonial-accent" />
          <div class="testimonial-content">
            <Icon name="ion:chatbubble-ellipses-outline" size="40" class="testimonial-icon" />
            <blockquote>
              „Много съм доволна от качеството и бързата доставка. Поръчах за първи
              път и определено ще се върна отново. Обслужването е на ниво!"
            </blockquote>
            <div class="testimonial-author">
              <div class="author-avatar">М</div>
              <div>
                <strong>Мария Иванова</strong>
                <span>Доволен клиент</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══════════════════ FEATURES ═══════════════════ -->
    <section class="features-section">
      <div class="container">
        <div class="features-grid">
          <div class="feature-card">
            <div class="feature-icon">
              <Icon name="ion:rocket-outline" size="28" />
            </div>
            <h3>Бърза доставка</h3>
            <p>Доставка с Еконт до 1-2 работни дни в цяла България</p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">
              <Icon name="ion:refresh-outline" size="28" />
            </div>
            <h3>14 дни връщане</h3>
            <p>Безпроблемно връщане в рамките на 14 дни от получаването</p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">
              <Icon name="ion:shield-checkmark-outline" size="28" />
            </div>
            <h3>Сигурно плащане</h3>
            <p>Защитени плащания с карта, PayPal или наложен платеж</p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">
              <Icon name="ion:headset-outline" size="28" />
            </div>
            <h3>Поддръжка 24/7</h3>
            <p>Нашият екип е на разположение по всяко време</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══════════════════ NEWSLETTER CTA ═══════════════════ -->
    <section class="newsletter-section">
      <div class="container">
        <div class="newsletter-card">
          <h2>Абонирай се за нашия бюлетин</h2>
          <p>Получавай първи информация за нови продукти, промоции и отстъпки</p>
          <form class="newsletter-form" @submit.prevent>
            <input type="email" placeholder="Въведи имейл адрес..." />
            <button type="submit">Абонирай се</button>
          </form>
        </div>
      </div>
    </section>
  </main>
</template>

<style scoped>
/* ═══════════════════ BASE ═══════════════════ */
.landing-page {
  --lp-green: #2d6a4f;
  --lp-green-light: #40916c;
  --lp-green-dark: #1b4332;
  --lp-cream: #f5f3ed;
  --lp-cream-dark: #e8e4da;
  --lp-yellow: #f4c430;
  --lp-text: #1a1a2e;
  --lp-text-muted: #555;
  --lp-radius: 12px;
}

.landing-page section {
  padding: 4rem 0;
}

/* ═══════════════════ SECTION TITLES ═══════════════════ */
.section-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--lp-text);
  line-height: 1.2;
}

.dark .section-title {
  color: #fff;
}

.section-subtitle {
  color: var(--lp-text-muted);
  margin-top: 0.25rem;
  font-size: 0.95rem;
}

.dark .section-subtitle {
  color: #aaa;
}

.section-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
}

.view-all-link {
  display: flex;
  align-items: center;
  gap: 4px;
  color: var(--lp-green);
  font-weight: 600;
  font-size: 0.9rem;
  transition: gap 0.2s;
}

.view-all-link:hover {
  gap: 8px;
}

/* ═══════════════════ HERO ═══════════════════ */
.hero-section {
  background: var(--lp-cream);
  padding: 3rem 0 4rem;
  overflow: hidden;
}

.dark .hero-section {
  background: #1a1a2e;
}

.hero-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 2rem;
  align-items: center;
}

@media (min-width: 768px) {
  .hero-grid {
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
  }
}

.hero-title {
  font-size: clamp(2.2rem, 5vw, 3.5rem);
  font-weight: 800;
  line-height: 1.1;
  color: var(--lp-text);
  letter-spacing: -0.02em;
}

.dark .hero-title {
  color: #fff;
}

.hero-subtitle {
  margin-top: 1rem;
  font-size: 1.05rem;
  line-height: 1.6;
  color: var(--lp-text-muted);
  max-width: 440px;
}

.dark .hero-subtitle {
  color: #bbb;
}

.hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-top: 1.75rem;
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 0.75rem 1.75rem;
  background: var(--lp-green);
  color: #fff;
  font-weight: 600;
  border-radius: 50px;
  transition: background 0.2s, transform 0.15s;
  font-size: 0.95rem;
}

.btn-primary:hover {
  background: var(--lp-green-dark);
  transform: translateY(-1px);
}

.btn-ghost {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 0.75rem 1.75rem;
  color: var(--lp-green);
  font-weight: 600;
  border: 2px solid var(--lp-green);
  border-radius: 50px;
  transition: all 0.2s;
  font-size: 0.95rem;
}

.btn-ghost:hover {
  background: var(--lp-green);
  color: #fff;
}

.hero-stats {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  margin-top: 2.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid rgba(0, 0, 0, 0.08);
}

.dark .hero-stats {
  border-top-color: rgba(255, 255, 255, 0.1);
}

.stat-number {
  display: block;
  font-size: 1.3rem;
  font-weight: 800;
  color: var(--lp-green);
}

.stat-label {
  font-size: 0.8rem;
  color: var(--lp-text-muted);
}

.dark .stat-label {
  color: #aaa;
}

.stat-divider {
  width: 1px;
  height: 36px;
  background: rgba(0, 0, 0, 0.1);
}

.dark .stat-divider {
  background: rgba(255, 255, 255, 0.1);
}

.hero-visual {
  display: flex;
  justify-content: center;
}

.hero-blob {
  position: relative;
  width: 100%;
  max-width: 480px;
  aspect-ratio: 1;
  border-radius: 40% 60% 60% 40% / 60% 30% 70% 40%;
  overflow: hidden;
  background: var(--lp-green);
}

.hero-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* ═══════════════════ HOW IT WORKS ═══════════════════ */
.how-section {
  text-align: center;
}

.steps-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.25rem;
  margin-top: 2.5rem;
}

@media (min-width: 768px) {
  .steps-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

.step-card {
  background: var(--lp-cream);
  border-radius: var(--lp-radius);
  padding: 1.75rem 1.25rem;
  transition: transform 0.2s, box-shadow 0.2s;
}

.dark .step-card {
  background: #1e1e32;
}

.step-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
}

.step-icon {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--lp-green);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1rem;
}

.step-card h3 {
  font-size: 1rem;
  font-weight: 700;
  color: var(--lp-text);
  margin-bottom: 0.35rem;
}

.dark .step-card h3 {
  color: #fff;
}

.step-card p {
  font-size: 0.85rem;
  color: var(--lp-text-muted);
  line-height: 1.4;
}

.dark .step-card p {
  color: #aaa;
}

/* ═══════════════════ CATEGORIES ═══════════════════ */
.categories-section {
  background: var(--lp-cream);
}

.dark .categories-section {
  background: #1a1a2e;
}

/* ═══════════════════ POPULAR PRODUCTS ═══════════════════ */
.products-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.25rem;
}

@media (min-width: 768px) {
  .products-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

/* ═══════════════════ TESTIMONIAL ═══════════════════ */
.testimonial-section {
  background: var(--lp-cream);
}

.dark .testimonial-section {
  background: #1a1a2e;
}

.testimonial-card {
  display: flex;
  gap: 0;
  border-radius: var(--lp-radius);
  overflow: hidden;
  background: #fff;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);
}

.dark .testimonial-card {
  background: #1e1e32;
}

.testimonial-accent {
  width: 8px;
  min-height: 100%;
  background: var(--lp-green);
  flex-shrink: 0;
}

.testimonial-content {
  padding: 2.5rem;
}

.testimonial-icon {
  color: var(--lp-green);
  opacity: 0.6;
  margin-bottom: 1rem;
}

.testimonial-content blockquote {
  font-size: 1.1rem;
  line-height: 1.7;
  color: var(--lp-text);
  font-style: italic;
}

.dark .testimonial-content blockquote {
  color: #ddd;
}

.testimonial-author {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-top: 1.5rem;
}

.author-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: var(--lp-green);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 1.1rem;
}

.testimonial-author strong {
  display: block;
  font-size: 0.95rem;
  color: var(--lp-text);
}

.dark .testimonial-author strong {
  color: #fff;
}

.testimonial-author span {
  font-size: 0.8rem;
  color: var(--lp-text-muted);
}

.dark .testimonial-author span {
  color: #aaa;
}

/* ═══════════════════ FEATURES ═══════════════════ */
.features-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.25rem;
}

@media (min-width: 768px) {
  .features-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

.feature-card {
  text-align: center;
  padding: 2rem 1.25rem;
  border-radius: var(--lp-radius);
  border: 1px solid rgba(0, 0, 0, 0.06);
  background: #fff;
  transition: transform 0.2s, box-shadow 0.2s;
}

.dark .feature-card {
  background: #1e1e32;
  border-color: rgba(255, 255, 255, 0.06);
}

.feature-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
}

.feature-icon {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--lp-cream);
  color: var(--lp-green);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1rem;
}

.dark .feature-icon {
  background: rgba(45, 106, 79, 0.15);
}

.feature-card h3 {
  font-size: 1rem;
  font-weight: 700;
  color: var(--lp-text);
  margin-bottom: 0.35rem;
}

.dark .feature-card h3 {
  color: #fff;
}

.feature-card p {
  font-size: 0.85rem;
  color: var(--lp-text-muted);
  line-height: 1.4;
}

.dark .feature-card p {
  color: #aaa;
}

/* ═══════════════════ NEWSLETTER ═══════════════════ */
.newsletter-section {
  padding-bottom: 5rem;
}

.newsletter-card {
  text-align: center;
  background: var(--lp-green);
  color: #fff;
  border-radius: 20px;
  padding: 3.5rem 2rem;
}

.newsletter-card h2 {
  font-size: 1.6rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
}

.newsletter-card > p {
  opacity: 0.85;
  font-size: 0.95rem;
  max-width: 480px;
  margin: 0 auto;
}

.newsletter-form {
  display: flex;
  gap: 0;
  max-width: 440px;
  margin: 1.75rem auto 0;
  border-radius: 50px;
  overflow: hidden;
  background: #fff;
}

.newsletter-form input {
  flex: 1;
  border: none;
  outline: none;
  padding: 0.85rem 1.25rem;
  font-size: 0.9rem;
  color: #333;
  background: transparent;
  min-width: 0;
}

.newsletter-form button {
  padding: 0.85rem 1.5rem;
  background: var(--lp-yellow);
  color: var(--lp-text);
  font-weight: 700;
  font-size: 0.85rem;
  border: none;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.2s;
}

.newsletter-form button:hover {
  background: #e6b800;
}
</style>
