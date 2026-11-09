<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php include __DIR__.'/../layouts/header.php'; ?>
<?php
// Swap these URLs for your own photography at any time. The coloured gradients behind
// each image are the fallback, so the layout still looks intentional if an image fails to load.
$home_images = [
	'hero'      => 'https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?auto=format&fit=crop&w=2200&q=85',
	'transport' => 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?auto=format&fit=crop&w=1200&q=80',
	'stays'     => 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&w=1200&q=80',
	'journal'   => 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?auto=format&fit=crop&w=1200&q=80',
];
?>
<section class="hero" aria-labelledby="hero-title">
	<div class="hero-top">
		<p class="wordmark" aria-hidden="true"><span class="line" data-split>Eco</span><span class="line" data-split>Trip</span></p>
		<div class="hero-lede" data-reveal style="--d: 700ms">
			<p class="eyebrow">A more thoughtful way to travel</p>
			<h1 id="hero-title">Plan a trip that leaves less behind.</h1>
			<p class="hero-copy">Find a route that fits your pace, a stay that shares your values, and room for the moments in between.</p>
			<div class="hero-actions">
				<a class="btn" href="<?= site_url('plan') ?>" data-magnetic>Start planning</a>
				<a class="hero-secondary" href="<?= site_url('stays') ?>" data-roll>Explore green stays</a>
			</div>
		</div>
	</div>
	<div class="hero-media" data-reveal style="--d: 900ms">
		<div class="badge" aria-hidden="true">
			<svg viewBox="0 0 100 100"><defs><path id="badge-circle" d="M50,50 m-37,0 a37,37 0 1,1 74,0 a37,37 0 1,1 -74,0"/></defs><text><textPath href="#badge-circle" textLength="228" lengthAdjust="spacing">Plan · Stay · Travel light · </textPath></text></svg>
		</div>
		<div class="hero-frame" data-grow>
			<img src="<?= htmlspecialchars($home_images['hero'], ENT_QUOTES, 'UTF-8') ?>" alt="A quiet mountain lake at sunrise" width="2200" height="1467" fetchpriority="high" data-parallax="0.09">
			<p class="hero-caption"><small>Slow travel</small>Lake &amp; mountain mornings</p>
			<p class="hero-count">03<small>ways to travel lighter</small></p>
		</div>
	</div>
</section>

<div class="marquee" aria-hidden="true">
	<div class="marquee-track">
		<span>Travel light</span><span>Stay well</span><span>Keep close</span><span>Go slowly</span>
		<span>Travel light</span><span>Stay well</span><span>Keep close</span><span>Go slowly</span>
	</div>
</div>

<section class="home-section" aria-labelledby="journey-title">
	<div class="section-heading">
		<p class="eyebrow" data-reveal>The journey, considered</p>
		<h2 id="journey-title" data-reveal>Good trips are made of good choices.</h2>
		<p data-reveal style="--d: 120ms">Thoughtful options for getting there, staying well, and remembering it all.</p>
	</div>
	<div class="feature-grid" data-stagger>
		<a class="feature-card" href="<?= site_url('transport') ?>" data-reveal>
			<div class="feature-media" data-reveal="img">
				<img src="<?= htmlspecialchars($home_images['transport'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" width="1200" height="1500">
				<span class="feature-index">01 / GET THERE</span>
			</div>
			<h3>Take the scenic route.</h3>
			<p>Explore buses, trains, and bike-friendly ways to reach your next stop.</p>
			<span class="feature-link"><span data-roll>Explore routes</span><i aria-hidden="true">→</i></span>
		</a>
		<a class="feature-card" href="<?= site_url('stays') ?>" data-reveal>
			<div class="feature-media" data-reveal="img">
				<img src="<?= htmlspecialchars($home_images['stays'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" width="1200" height="1500">
				<span class="feature-index">02 / STAY WELL</span>
			</div>
			<h3>Stay somewhere with care.</h3>
			<p>Find welcoming places that put a little more thought into their footprint.</p>
			<span class="feature-link"><span data-roll>Browse green stays</span><i aria-hidden="true">→</i></span>
		</a>
		<a class="feature-card" href="<?= site_url('journal') ?>" data-reveal>
			<div class="feature-media" data-reveal="img">
				<img src="<?= htmlspecialchars($home_images['journal'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" width="1200" height="1500">
				<span class="feature-index">03 / KEEP CLOSE</span>
			</div>
			<h3>Bring the good bits home.</h3>
			<p>Save the small discoveries and stories that make a trip yours.</p>
			<span class="feature-link"><span data-roll>Open your journal</span><i aria-hidden="true">→</i></span>
		</a>
	</div>
</section>

<section class="statement" aria-label="Our belief">
	<div class="statement-inner">
		<p class="eyebrow" data-reveal>We believe</p>
		<p class="big-quote" data-words>A journey deserves just as much care as the place you leave behind.</p>
	</div>
</section>

<section class="philosophy" aria-labelledby="philosophy-title">
	<div class="philosophy-head" data-reveal>
		<p class="eyebrow">How we travel</p>
		<h2 id="philosophy-title">Our philosophy</h2>
	</div>
	<ol class="philosophy-list">
		<li data-reveal="line">
			<span class="num">1</span>
			<h3>Lighter</h3>
			<p>Low-carbon routes by bus, train and bike, so the way there becomes part of the trip rather than something to get through.</p>
		</li>
		<li data-reveal="line" style="--d: 100ms">
			<span class="num">2</span>
			<h3>Warmer</h3>
			<p>Eco-rated stays sit alongside standard hotels, so you can choose somewhere that treats people and place with equal care.</p>
		</li>
		<li data-reveal="line" style="--d: 200ms">
			<span class="num">3</span>
			<h3>Closer</h3>
			<p>A photo journal and gentle reminders keep the small discoveries from slipping away once you are home.</p>
		</li>
	</ol>
</section>

<section class="home-cta" aria-label="Plan your next trip" data-reveal>
	<p>Make space for a <em>different</em> kind of getaway.</p>
	<a class="btn btn-light btn-lg" href="<?= site_url('plan') ?>" data-magnetic>Plan your trip</a>
</section>
<?php include __DIR__.'/../layouts/footer.php'; ?>
