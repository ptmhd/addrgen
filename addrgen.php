<?php
/**
 * Copyright (c) 2012 Matyas Danter
 * Copyright (c) 2012 Chris Savery
 * Copyright (c) 2013 Pavol Rusnak
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES
 * OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,
 * ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

class Curve {

	protected $a = 0;
	protected $b = 0;
	protected $prime = 0;

	public function __construct($prime, $a, $b) {
		$this->a = $a;
		$this->b = $b;
		$this->prime = $prime;
	}

	public function contains($x, $y) {
		return !gmp_cmp(gmp_mod(gmp_sub(gmp_pow($y, 2), gmp_add(gmp_add(gmp_pow($x, 3), gmp_mul($this->a, $x)), $this->b)), $this->prime), 0);
	}

	public static function cmp(Curve $cp1, Curve $cp2) {
		return (gmp_cmp($cp1->a, $cp2->a) || gmp_cmp($cp1->b, $cp2->b) || gmp_cmp($cp1->prime, $cp2->prime));
	}

	public function getA() {
		return $this->a;
	}

	public function getB() {
		return $this->b;
	}

	public function getPrime() {
		return $this->prime;
	}
}

class Point {

	public $curve;
	public $x;
	public $y;
	public $order;
	public static $infinity = 'infinity';

	public function __construct(Curve $curve, $x, $y, $order = null) {
		$this->curve = $curve;
		$this->x = $x;
		$this->y = $y;
		$this->order = $order;


		if (isset($this->curve) && ($this->curve instanceof Curve)) {
			if (!$this->curve->contains($this->x, $this->y)) {
				throw new ErrorException("Curve" . print_r($this->curve, true) . " does not contain point ( " . $x . " , " . $y . " )");
			}

			if ($this->order != null) {
				if (self::cmp(self::mul($order, $this), self::$infinity) != 0) {
					throw new ErrorException("SELF * ORDER MUST EQUAL INFINITY");
				}
			}
		}
	}

	public static function cmp($p1, $p2) {
			if (!($p1 instanceof Point)) {
				if (($p2 instanceof Point))
					return 1;
				if (!($p2 instanceof Point))
					return 0;
			}
			if (!($p2 instanceof Point)) {
				if (($p1 instanceof Point))
					return 1;
				if (!($p1 instanceof Point))
					return 0;
			}
			return (gmp_cmp($p1->x, $p2->x) || gmp_cmp($p1->y, $p2->y) || Curve::cmp($p1->curve, $p2->curve));
	}

	public static function add($p1, $p2) {

		if (self::cmp($p2, self::$infinity) == 0 && ($p1 instanceof Point)) {
			return $p1;
		}
		if (self::cmp($p1, self::$infinity) == 0 && ($p2 instanceof Point)) {
			return $p2;
		}
		if (self::cmp($p1, self::$infinity) == 0 && self::cmp($p2, self::$infinity) == 0) {
			return self::$infinity;
		}

		if (Curve::cmp($p1->curve, $p2->curve) == 0) {
			if (gmp_mod(gmp_cmp($p1->x, $p2->x), $p1->curve->getPrime()) == 0) {
				if (gmp_mod(gmp_add($p1->y, $p2->y), $p1->curve->getPrime()) == 0) {
					return self::$infinity;
				} else {
					return self::double($p1);
				}
			}

			$p = $p1->curve->getPrime();
			$l = gmp_mul(gmp_sub($p2->y, $p1->y), gmp_invert(gmp_sub($p2->x, $p1->x), $p));
			$x3 = gmp_mod(gmp_sub(gmp_sub(gmp_pow($l, 2), $p1->x), $p2->x), $p);
			$y3 = gmp_mod(gmp_sub(gmp_mul($l, gmp_sub($p1->x, $x3)), $p1->y), $p);
			$p3 = new Point($p1->curve, $x3, $y3);
			return $p3;
		} else {
			throw new ErrorException("Elliptic Curves do not match.");
		}
	}

	public static function mul($x2, Point $p1) {
		$e = $x2;
		if (self::cmp($p1, self::$infinity) == 0) {
			return self::$infinity;
		}
		if ($p1->order != null) {
			$e = gmp_mod($e, $p1->order);
		}
		if (gmp_cmp($e, 0) == 0) {
			return self::$infinity;
		}
		if (gmp_cmp($e, 0) > 0) {
			$e3 = gmp_mul(3, $e);
			$negative_self = new Point($p1->curve, $p1->x, gmp_sub(0, $p1->y), $p1->order);
			$i = gmp_div(self::leftmost_bit($e3), 2);
			$result = $p1;
			while (gmp_cmp($i, 1) > 0) {
				$result = self::double($result);
				if (gmp_cmp(gmp_and($e3, $i), 0) != 0 && gmp_cmp(gmp_and($e, $i), 0) == 0) {
					$result = self::add($result, $p1);
				}
				if (gmp_cmp(gmp_and($e3, $i), 0) == 0 && gmp_cmp(gmp_and($e, $i), 0) != 0) {
					$result = self::add($result, $negative_self);
				}
				$i = gmp_div($i, 2);
			}
			return $result;
		}
	}

	public static function leftmost_bit($x) {
		if (gmp_cmp($x, 0) > 0) {
			$result = 1;
			while (gmp_cmp($result, $x) < 0 || gmp_cmp($result, $x) == 0) {
				$result = gmp_mul(2, $result);
			}
			return gmp_div($result, 2);
		}
	}

	public static function double(Point $p1) {
		$p = $p1->curve->getPrime();
		$a = $p1->curve->getA();
		$inverse = gmp_invert(gmp_mul(2, $p1->y), $p);
		$three_x2 = gmp_mul(3, gmp_pow($p1->x, 2));
		$l = gmp_mod(gmp_mul(gmp_add($three_x2, $a), $inverse), $p);
		$x3 = gmp_mod(gmp_sub(gmp_pow($l, 2), gmp_mul(2, $p1->x)), $p);
		$y3 = gmp_mod(gmp_sub(gmp_mul($l, gmp_sub($p1->x, $x3)), $p1->y), $p);
		if (gmp_cmp(0, $y3) > 0)
			$y3 = gmp_add($p, $y3);
		$p3 = new Point($p1->curve, $x3, $y3);
		return $p3;
	}

	public function getX() {
		return $this->x;
	}

	public function getY() {
		return $this->y;
	}

	public function getCurve() {
		return $this->curve;
	}

	public function getOrder() {
		return $this->order;
	}
}

function addr_from_mpk($mpk, $index)
{
	// create the ecc curve
	$_p  = gmp_init('0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F', 16);
	$_r  = gmp_init('0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141', 16);
	$_b  = gmp_init('0x0000000000000000000000000000000000000000000000000000000000000007', 16);
	$_Gx = gmp_init('0x79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798', 16);
	$_Gy = gmp_init('0x483ada7726a3c4655da4fbfc0e1108a8fd17b448a68554199c47d08ffb10d4b8', 16);
	$curve = new Curve($_p, 0, $_b);
	$gen = new Point($curve, $_Gx, $_Gy, $_r);

	// prepare the input values
	$x = gmp_init(substr($mpk, 0, 64), 16);
	$y = gmp_init(substr($mpk, 64, 64), 16);
	$z = gmp_init(hash('sha256', hash('sha256', $index . ':0:' . pack('H*', $mpk), TRUE)), 16);

	// generate the new public key based off master and sequence points
	$pt = Point::add(new Point($curve, $x, $y), Point::mul($z, $gen) );
	$keystr = "\x04"
	        . pack('H*', str_pad(gmp_strval($pt->getX(), 16), 64, '0', STR_PAD_LEFT))
	        . pack('H*', str_pad(gmp_strval($pt->getY(), 16), 64, '0', STR_PAD_LEFT));
	$vh160 =  "\x00" . hash('ripemd160', hash('sha256', $keystr, TRUE), TRUE);
	$addr = $vh160 . substr(hash('sha256', hash('sha256', $vh160, TRUE), TRUE), 0, 4);

	$num = reset(unpack('H*', $addr));
	$num = gmp_strval(gmp_init($num, 16), 58);
	$num = strtr($num, '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuv', '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz');

	$pad = ''; $n = 0;
	while ($addr[$n++] == "\x00") $pad .= '1';

	return $pad . $num;
}

?>
