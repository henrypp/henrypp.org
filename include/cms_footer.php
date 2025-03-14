			</main>

			<footer class="footer">
				<p>(c) <?php printf('%s %s', $cms->get_cfg('date'), $cms->get_cfg('author')); ?>. All rights reversed.</p>
			</footer>
		</div>

		<script>
			document.getElementById('nav').addEventListener("click", function(e) {
				(function(e) {
					document.getElementById('lside').classList.toggle("lside-show"); return false;
				}).call(document.getElementById('nav'), e);
			});
		</script>
	</body>
</html>

<!-- old-school in my blood, 2011 comming back (15/10/15) -->
<!-- upd: (26/07/17) -->
