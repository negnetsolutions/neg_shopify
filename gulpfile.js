var gulp         = require("gulp"),
    sass         = require("gulp-sass")(require('sass')),
    autoprefixer = require("gulp-autoprefixer"),
    cleanCSS    =  require('gulp-clean-css'),
    exec = require('child_process').exec
    ;

var config = {
  jsPattern: 'js/**/*.js',
  sassPattern: 'scss/**/*.*',
  cssPath: 'css'
};

// Compile SCSS files to CSS
gulp.task("scss", function (done) {
  var stream = gulp.src(config.sassPattern)
    .pipe(sass({
      outputStyle : config.dev ? "expanded" : "compressed"
    }))
    .pipe(autoprefixer())
    .pipe(cleanCSS({compatibility: '*'}))
    .pipe(gulp.dest(config.cssPath))
  ;

  return stream;
});

// Watch asset folder for changes
gulp.task("watch", gulp.series("scss", function () {
    gulp.watch("scss/**/*").on('change', gulp.series("scss"));
}));

gulp.task("default", gulp.series("watch", function (done) { done(); }));
