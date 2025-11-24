const gulp = require("gulp")
const sass = require("gulp-sass")(require("sass"))
const autoprefixer = require("gulp-autoprefixer")
const cleanCSS = require("gulp-clean-css")
const concat = require("gulp-concat")
const uglify = require("gulp-uglify")
const rename = require("gulp-rename")
const imagemin = require("gulp-imagemin")
const browserSync = require("browser-sync").create()

// Paths configuration
const paths = {
  styles: {
    src: "assets/scss/**/*.scss",
    dest: "assets/css/",
  },
  scripts: {
    src: "assets/js/src/**/*.js",
    dest: "assets/js/",
  },
  images: {
    src: "assets/images/src/**/*",
    dest: "assets/images/",
  },
}

// Compile SCSS to CSS
function styles() {
  return gulp
    .src(paths.styles.src)
    .pipe(sass().on("error", sass.logError))
    .pipe(
      autoprefixer({
        cascade: false,
      }),
    )
    .pipe(gulp.dest(paths.styles.dest))
    .pipe(cleanCSS())
    .pipe(
      rename({
        suffix: ".min",
      }),
    )
    .pipe(gulp.dest(paths.styles.dest))
    .pipe(browserSync.stream())
}

// Concatenate and minify JavaScript
function scripts() {
  return gulp
    .src(paths.scripts.src)
    .pipe(concat("frontend.js"))
    .pipe(gulp.dest(paths.scripts.dest))
    .pipe(uglify())
    .pipe(
      rename({
        suffix: ".min",
      }),
    )
    .pipe(gulp.dest(paths.scripts.dest))
    .pipe(browserSync.stream())
}

// Optimize images
function images() {
  return gulp
    .src(paths.images.src)
    .pipe(
      imagemin([
        imagemin.gifsicle({ interlaced: true }),
        imagemin.mozjpeg({ quality: 75, progressive: true }),
        imagemin.optipng({ optimizationLevel: 5 }),
        imagemin.svgo({
          plugins: [
            {
              name: "removeViewBox",
              active: true,
            },
            {
              name: "cleanupIDs",
              active: false,
            },
          ],
        }),
      ]),
    )
    .pipe(gulp.dest(paths.images.dest))
}

// Watch files for changes
function watchFiles() {
  gulp.watch(paths.styles.src, styles)
  gulp.watch(paths.scripts.src, scripts)
  gulp.watch(paths.images.src, images)
}

// BrowserSync for live reload (optional)
function serve() {
  browserSync.init({
    proxy: "http://localhost/wordpress", // Change to your local WordPress URL
    notify: false,
  })

  gulp.watch(paths.styles.src, styles)
  gulp.watch(paths.scripts.src, scripts)
  gulp.watch("**/*.php").on("change", browserSync.reload)
}

// Export tasks
exports.styles = styles
exports.scripts = scripts
exports.images = images
exports.watch = watchFiles
exports.serve = serve

// Default task
exports.default = gulp.series(gulp.parallel(styles, scripts, images), watchFiles)

// Build task for production
exports.build = gulp.parallel(styles, scripts, images)
